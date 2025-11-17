<?php
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /** Enregistre ici tes commandes */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        // require base_path('routes/console.php'); // optionnel
    }

    /** Planifie les tâches */
    protected function schedule(Schedule $schedule): void
    {
        // exécute notre commande toutes les heures
        $schedule->command('smartkids:expire-payments')->hourly();
        // ou: $schedule->command('smartkids:expire-payments')->dailyAt('23:55');
    }
}
