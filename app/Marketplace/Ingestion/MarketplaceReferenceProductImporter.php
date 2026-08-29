<?php

namespace App\Marketplace\Ingestion;

use App\Enums\MarketplaceAccessType;
use App\Marketplace\MarketplaceSourceManager;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceItem;
use App\Models\MarketplaceSourceVersion;
use Illuminate\Support\Facades\DB;

class MarketplaceReferenceProductImporter
{
    public function __construct(
        private readonly MarketplacePsdInspector $inspector,
        private readonly MarketplacePackageBuilder $packages,
        private readonly MarketplaceSourceManager $sources,
        private readonly MarketplacePreviewManager $previews,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $fonts
     * @param  array<string, mixed>  $rights
     */
    public function importSundayService(string $psdPath, string $previewPath, array $fonts = [], array $rights = []): MarketplaceReferenceImportResult
    {
        $inspection = $this->inspector->inspect($psdPath);
        $readme = $this->readme($fonts, $rights);
        $package = $this->packages->build($inspection, $readme);

        $category = MarketplaceCategory::firstOrCreate(
            ['slug' => 'services'],
            ['name' => 'Services', 'description' => 'Professional source designs for church services.'],
        );
        $item = MarketplaceItem::withTrashed()->firstOrCreate(
            ['slug' => 'sunday-service'],
            [
                'marketplace_category_id' => $category->id,
                'title' => 'Sunday Service',
                'short_description' => 'A professionally prepared Sunday service PSD for external editing.',
                'description' => 'Download the source package and edit it in Adobe Photoshop or a compatible PSD application.',
                'access_type' => MarketplaceAccessType::FREE,
                'creator_name' => $rights['creator'] ?? null,
                'publisher_name' => 'Keryon',
            ],
        );

        $existing = $item->sourceVersions->first(function (MarketplaceSourceVersion $source) use ($inspection): bool {
            return data_get($source->compatibility_metadata, 'source_psd.sha256') === $inspection->sha256;
        });

        if ($existing !== null) {
            return new MarketplaceReferenceImportResult($item->fresh(), $existing, $inspection, $package, true);
        }

        $source = DB::transaction(function () use ($item, $inspection, $package, $fonts, $rights): MarketplaceSourceVersion {
            $lockedItem = MarketplaceItem::query()->lockForUpdate()->findOrFail($item->id);
            $version = ((int) $lockedItem->sourceVersions()->max('version')) + 1;

            return $this->sources->register($lockedItem, $version, $package->bytes, $package->filename, [
                'compatibility_metadata' => [
                    'source_psd' => $inspection->metadata(),
                    'package' => ['format' => 'zip', 'entries' => ['Sunday-Service.psd', 'README.txt']],
                    'editing_model' => 'external_psd_editor',
                ],
                'creator_name' => $rights['creator'] ?? null,
                'rightsholder_name' => $rights['rightsholder'] ?? null,
                'license_reference' => $rights['license_reference'] ?? 'pending-product-office-license',
                'licensing_metadata' => [
                    'status' => $rights['status'] ?? 'pending_publisher_declaration',
                    'redistribution_authority' => $rights['redistribution_authority'] ?? 'pending_publisher_declaration',
                ],
                'font_metadata' => [
                    'declaration_status' => $fonts === [] ? 'pending_publisher_declaration' : 'publisher_declared',
                    'fonts' => $fonts,
                    'font_files_bundled' => false,
                ],
            ]);
        });

        $this->previews->register($item, $source, $previewPath, 'Sunday Service Marketplace design preview');

        return new MarketplaceReferenceImportResult($item->fresh(), $source, $inspection, $package, false);
    }

    /** @param array<int, array<string, mixed>> $fonts @param array<string, mixed> $rights */
    private function readme(array $fonts, array $rights): string
    {
        $fontText = $fonts === []
            ? 'Font requirements were not supplied. Confirm required fonts before editing. No font files are included.'
            : implode("\n", array_map(fn (array $font): string => '- '.($font['family'] ?? 'Unspecified font').' (not bundled; obtain separately)', $fonts));

        return <<<TEXT
Keryon Design Marketplace

Design:
Sunday Service

Source:
Adobe Photoshop PSD

Editing:
Edit this design using Adobe Photoshop or a compatible application that supports PSD files.

Fonts:
{$fontText}

Important:
Font files are not included. Font dependency does not imply redistribution permission.

Licence:
Reference: {$this->value($rights['license_reference'] ?? null)}
Status: {$this->value($rights['status'] ?? null)}

Provided for use by the acquiring Keryon church subject to the applicable Keryon Marketplace licence. Final legal terms remain subject to Product Office approval.
TEXT;
    }

    private function value(mixed $value): string
    {
        return is_string($value) && $value !== '' ? $value : 'Pending publisher declaration';
    }
}
