<?php

use Illuminate\Database\Migrations\Migration;

class CreateSequencesValuesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('sequences_values', function($table)
		{
			$table->string('tablename');
			$table->string('columnname');
			$table->integer('lastvalue');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('sequences_values');
	}

}
