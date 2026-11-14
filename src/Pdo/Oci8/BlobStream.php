<?php

namespace Yajra\Pdo\Oci8;

use OCILob;
use Yajra\Pdo\Oci8;
use Yajra\Pdo\Oci8\Exceptions\Oci8Exception;

/** @internal Read-only PHP stream backed by an Oracle BLOB locator. */
final class BlobStream
{
    private const SCHEME = 'yajra-oci8-blob';

    private const READ_CHUNK_SIZE = 1024 * 1024;

    private static bool $registered = false;

    /** @var resource|null Populated by PHP before stream_open(). */
    public $context;

    private ?OCILob $lob = null;

    // Keep the connection alive even if the caller releases the statement.
    private ?Oci8 $connection = null;

    /** @return resource */
    public static function open(OCILob $lob, Oci8 $connection)
    {
        try {
            if (! self::$registered) {
                if (! stream_wrapper_register(self::SCHEME, self::class)) {
                    throw new Oci8Exception('Unable to register the Oracle BLOB stream wrapper.');
                }
                self::$registered = true;
            }

            if (! $lob->rewind()) {
                throw new Oci8Exception('Unable to rewind the BLOB.');
            }

            $context = stream_context_create([
                self::SCHEME => ['lob' => $lob, 'connection' => $connection],
            ]);
            $stream = fopen(self::SCHEME.'://lob', 'rb', false, $context);
            if ($stream === false) {
                throw new Oci8Exception('Unable to open the Oracle BLOB stream.');
            }

            // Buffer small PHP reads to reduce Oracle LOB round trips.
            stream_set_chunk_size($stream, self::READ_CHUNK_SIZE);

            return $stream;
        } catch (\Throwable $e) {
            $lob->free();
            throw $e;
        }
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        if ($mode !== 'rb' || ! is_resource($this->context)) {
            return false;
        }

        $settings = stream_context_get_options($this->context)[self::SCHEME] ?? [];
        if (! ($settings['lob'] ?? null) instanceof OCILob
            || ! ($settings['connection'] ?? null) instanceof Oci8) {
            return false;
        }

        $this->lob = $settings['lob'];
        $this->connection = $settings['connection'];

        return true;
    }

    public function stream_read(int $count): string
    {
        if ($count === 0 || $this->lob->eof()) {
            return '';
        }

        $chunk = $this->lob->read($count);
        if ($chunk === false || ($chunk === '' && ! $this->lob->eof())) {
            throw new Oci8Exception('Unable to read the Oracle BLOB stream.');
        }

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->lob->eof();
    }

    public function stream_tell(): int|false
    {
        return $this->lob->tell();
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        $origin = match ($whence) {
            SEEK_SET => OCI_SEEK_SET,
            SEEK_CUR => OCI_SEEK_CUR,
            SEEK_END => OCI_SEEK_END,
            default => null,
        };

        return $origin !== null && $this->lob->seek($offset, $origin);
    }

    public function stream_stat(): array|false
    {
        $size = $this->lob->size();
        if ($size === false) {
            return false;
        }

        $stat = array_fill_keys([
            'dev', 'ino', 'mode', 'nlink', 'uid', 'gid', 'rdev',
            'size', 'atime', 'mtime', 'ctime', 'blksize', 'blocks',
        ], 0);
        $stat['mode'] = 0100444;
        $stat['size'] = $size;

        return $stat;
    }

    public function stream_close(): void
    {
        try {
            if ($this->lob !== null) {
                $this->lob->free();
            }
        } finally {
            $this->lob = null;
            $this->connection = null;
            $this->context = null;
        }
    }
}
