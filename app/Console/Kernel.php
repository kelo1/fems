<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('notify:equipment-maintenance')->weeklyOn(1, '8:00');
        $schedule->command('notify:certificate-expiry')->weeklyOn(1, '8:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

    /**
     * The application's artisan commands.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\NotifyCertificateExpiry::class,
        \App\Console\Commands\NotifyEquipmentMaintenance::class,
    ];
}
