<?php namespace CRSCompany\Backster\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateCrscompanyBacksterBackups extends Migration
{
    public function up()
    {
        Schema::table('crscompany_backster_backups', function($table)
        {
            $table->integer('zip_id')->nullable();
            $table->integer('dump_id')->nullable();
            $table->dropColumn('files_zip');
            $table->dropColumn('db_dump');
        });
    }
    
    public function down()
    {
        Schema::table('crscompany_backster_backups', function($table)
        {
            $table->dropColumn('zip_id');
            $table->dropColumn('dump_id');
            $table->string('files_zip')->nullable();
            $table->string('db_dump')->nullable();
        });
    }
}
