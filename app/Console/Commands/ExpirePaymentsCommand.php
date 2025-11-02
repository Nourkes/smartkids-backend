<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PaymentHousekeepingService;

class ExpirePaymentsCommand extends Command
{
    protected $signature = 'smartkids:expire-payments';
    protected $description = 'Marque comme expirés les paiements en attente dépassés et applique le nettoyage.';

    public function handle(PaymentHousekeepingService $svc): int
    {
        $n = $svc->massExpireOverdue();
        $this->info("Expired & cleaned: $n paiements.");
        return self::SUCCESS;
    }
}
