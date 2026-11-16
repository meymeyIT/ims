<?php

namespace App\Console\Commands;

use App\Services\LowStockMonitor;
use Illuminate\Console\Command;

/**
 * php artisan ims:check-low-stock
 * Runs daily through the scheduler (routes/console.php).
 */
class CheckLowStock extends Command
{
    protected $signature = 'ims:check-low-stock';

    protected $description = 'Send low-stock alerts for products at or below their minimum stock (no duplicates)';

    public function handle(LowStockMonitor $monitor): int
    {
        $changed = $monitor->scan();

        $this->info("Low-stock check finished: {$changed} product(s) updated.");

        return self::SUCCESS;
    }
}
