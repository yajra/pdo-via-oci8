<?php

namespace Yajra\Pdo\Oci8\Exceptions;

use PDOException;

class Oci8Exception extends PDOException
{

    protected $oci_err;

    /***
     * Macig functions to create and output Exception class
     */
    public function __construct (string $message ="", int $code =0, PDOException $e =null, $oci_err =[]) {

        parent::__construct ($message, $code, $e);

        if ( !empty($oci_err) ) {
            $this->oci_err = $oci_err;
        } else {
            $this->oci_err['message']  = $message;
            $this->oci_err['code']     = '00000';
            $this->oci_err['sqltext']  = '';
            $this->oci_err['bindings'] = '[]';
        }

    }

    public function __toString() {
        return $this->getOciAllErrorDetail();
    }


    /***
     * Getters for variouis types of information from the returned OCI error array
     */
    public function getOciUserFriendlyMsg() {
        $msg_arr = explode ("\n", $this->oci_err['message']);
        return preg_replace ('/ORA-[0-9]{5}: /', '', $msg_arr[0]);
    }

    public function getOciErrorCode() {
        return $this->oci_err['code'];
    }

    public function getOriginalSql() {
        return $this->oci_err['sqltext'];
    }

    public function getOciErrorStack() {
        return $this->oci_err['message'];
    }

    public function getOciBindings() {
        return $this->oci_err['bindings'];
    }

    public function getOciAllErrorDetail() {
        return
            'Error Code  : ' . $this->oci_err['code']     . PHP_EOL .
            'Position    : ' . $this->oci_err['offset']   . PHP_EOL .
            'Statement   : ' . $this->oci_err['sqltext']  . PHP_EOL .
            'Bindings    : ' . $this->oci_err['bindings'] . PHP_EOL .
            'Error Stack : ' . PHP_EOL . $this->oci_err['message'];
    }

    public function getHtmlErrorStack() {
        $msg = $this->oci_err['message'];
        $msg = str_replace (chr(10), '<br />', $msg);
        return '<code>' . $msg . '</code>';
    }

}
