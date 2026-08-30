<?php

namespace App\Console\Commands;

use App\Commercial\Pricing\PricingCatalogBootstrapper;
use Illuminate\Console\Command;

class BootstrapPricingCatalog extends Command
{
    protected $signature = 'commercial:bootstrap-pricing';

    protected $description = 'Idempotently establish the governed Keryon regional pricing catalogue';

    public function handle(PricingCatalogBootstrapper $bootstrapper): int
    {
        $books = $bootstrapper->bootstrap();
        $this->components->info('Pricing catalogue ready: '.implode(', ', array_keys($books)));

        return self::SUCCESS;
    }
}
