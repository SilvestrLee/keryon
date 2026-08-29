<?php

namespace App\Console\Commands;

use App\Marketplace\Ingestion\MarketplaceReferenceProductImporter;
use Illuminate\Console\Command;

class ImportSundayServiceMarketplaceProduct extends Command
{
    protected $signature = 'marketplace:import-sunday-service {psd : Absolute or repository-relative PSD path} {preview : Genuine PNG/JPEG preview path}';

    protected $description = 'Import the trusted Sunday Service Marketplace reference product';

    public function handle(MarketplaceReferenceProductImporter $importer): int
    {
        $result = $importer->importSundayService((string) $this->argument('psd'), (string) $this->argument('preview'));

        $this->components->info($result->alreadyImported ? 'Identical Sunday Service source already imported.' : 'Sunday Service imported.');
        $this->table(['Item', 'Source version', 'PSD SHA-256', 'Package SHA-256'], [[
            $result->item->id,
            $result->source->version,
            $result->inspection->sha256,
            $result->package->sha256,
        ]]);

        return self::SUCCESS;
    }
}
