<?php namespace CRSCompany\Backster\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableCreateCrscompanyBacksterBackups extends Migration
{
    public function up()
    {
        Schema::create('crscompany_backster_backups', function($table)
        {
            $table->increments('id')->unsigned();
            $table->dateTime('time')->nullable();
            $table->string('files_zip')->nullable();
            $table->string('db_dump')->nullable();
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('crscompany_backster_backups');
    }
}
