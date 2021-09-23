<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
        'App\Console\Commands\BirthdayGreetingsEmail',
        'App\Console\Commands\GenerarReporteSemanal',
        'App\Console\Commands\GenerarReporteQuincenal',
        'App\Console\Commands\GenerarReporteMensual',
        'App\Console\Commands\DeleteReporte',
        'App\Console\Commands\GenerarReporte'
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();

        //$schedule->command('email:birthday_greetings')->dailyAt('11:52');

        //$schedule->command('generate:reporte_semanal')->hourly();

        //$schedule->command('generate:reporte_quincenal')->cron('0 * 15 * *');

        //$schedule->command('generate:reporte_mensual')->cron('0 * 1 * *');

        $schedule->command('delete:reporte')->dailyAt('03:00');

        $schedule->command('generate:reporte')->hourly();
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
}
