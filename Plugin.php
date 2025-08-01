<?php namespace CRSCompany\Backster;

use System\Classes\PluginBase;
use CRSCompany\Backster\Classes\BackupManager;

/**
 * Plugin class
 */
class Plugin extends PluginBase
{
    /**
     * register method, called when the plugin is first registered.
     */
    public function register()
    {
    }

    /**
     * boot method, called right before the request route.
     */
    public function boot()
    {
    }

    /**
     * registerComponents used by the frontend.
     */
    public function registerComponents()
    {
    }

    /**
     * registerSettings used by the backend.
     */
    public function registerSettings()
    {
    }

    /**
     * Register any scheduled tasks for this plugin.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     */
    public function registerSchedule($schedule)
    {
    // Schedule the backup to run daily at 2:00 AM
        $schedule->call(function () {
            BackupManager::createCompleteBackup('daily');
        })->daily()->at('2:00');

    // Schedule the backup to run weekly on Sundays at 3:00 AM
        $schedule->call(function () {
            BackupManager::createCompleteBackup('weekly');
        })->weekly()->sundays()->at('3:00');
    }
}
