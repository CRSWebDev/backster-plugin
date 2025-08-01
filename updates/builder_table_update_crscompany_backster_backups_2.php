<?php namespace CRSCompany\Backster\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class BuilderTableUpdateCrscompanyBacksterBackups2 extends Migration
{
    public function up()
    {
        Schema::table('crscompany_backster_backups', function($table)
        {
            $table->string('zip_url')->nullable();
            $table->string('dump_url')->nullable();
            $table->dropColumn('zip_id');
            $table->dropColumn('dump_id');
        });
    }
    
    public function down()
    {
        Schema::table('crscompany_backster_backups', function($table)
        {
            $table->dropColumn('zip_url');
            $table->dropColumn('dump_url');
            $table->integer('zip_id')->nullable();
            $table->integer('dump_id')->nullable();
        });
    }
}
