<?php

namespace App\Console\Commands;

use App\Commercial\Catalog\CommercialCatalogBootstrapper;
use Illuminate\Console\Command;

class BootstrapCommercialCatalog extends Command
{
    protected $signature = 'commercial:bootstrap-catalog';

    protected $description = 'Idempotently establish the governed Keryon product catalogue without prices';

    public function handle(CommercialCatalogBootstrapper $bootstrapper): int
    {
        $version = $bootstrapper->bootstrap();
        $this->components->info("Catalogue ready: {$version->plan->name} {$version->version_code}");

        return self::SUCCESS;
    }
}
