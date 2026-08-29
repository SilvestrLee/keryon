<?php

namespace Tests\Feature\Marketplace;

use App\Enums\MarketplaceAccessType;
use App\Enums\MarketplacePublicationStatus;
use App\Enums\MarketplaceRightsStatus;
use App\Enums\MarketplaceSourceAvailability;
use App\Marketplace\Ingestion\MarketplacePackageBuilder;
use App\Marketplace\Ingestion\MarketplacePsdInspector;
use App\Marketplace\Ingestion\MarketplaceReferenceProductImporter;
use App\Models\MarketplacePreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Concerns\VerifiesMarketplaceRights;
use Tests\TestCase;
use ZipArchive;

class MarketplacePsdIngestionTest extends TestCase
{
    use RefreshDatabase;
    use VerifiesMarketplaceRights;

    /** @var array<int, string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('marketplace');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_psd_inspection_reads_bounded_header_and_sha256(): void
    {
        $bytes = $this->psdBytes(width: 1260, height: 1260);
        $inspection = app(MarketplacePsdInspector::class)->inspect($this->temporary('source.psd', $bytes));

        $this->assertSame(1, $inspection->version);
        $this->assertSame(3, $inspection->channels);
        $this->assertSame(1260, $inspection->width);
        $this->assertSame(1260, $inspection->height);
        $this->assertSame(8, $inspection->depth);
        $this->assertSame(3, $inspection->colorMode);
        $this->assertSame(hash('sha256', $bytes), $inspection->sha256);
    }

    public function test_invalid_empty_and_excessive_psd_sources_are_rejected(): void
    {
        foreach (['empty.psd' => '', 'invalid.psd' => 'not-a-psd'] as $name => $bytes) {
            try {
                app(MarketplacePsdInspector::class)->inspect($this->temporary($name, $bytes));
                $this->fail("{$name} was accepted.");
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        config()->set('marketplace.max_psd_bytes', 26);
        $this->expectException(ValidationException::class);
        app(MarketplacePsdInspector::class)->inspect($this->temporary('large.psd', $this->psdBytes()));
    }

    public function test_package_contains_only_allowlisted_psd_and_readme_with_independent_checksum(): void
    {
        $inspection = app(MarketplacePsdInspector::class)->inspect($this->temporary('source.psd', $this->psdBytes()));
        $package = app(MarketplacePackageBuilder::class)->build($inspection, 'Keryon Marketplace README');
        $path = $this->temporary('package.zip', $package->bytes);
        $archive = new ZipArchive;

        $this->assertTrue($archive->open($path));
        $this->assertSame(['Sunday-Service.psd', 'README.txt'], [$archive->getNameIndex(0), $archive->getNameIndex(1)]);
        $this->assertSame($inspection->sha256, hash('sha256', $archive->getFromName('Sunday-Service.psd')));
        $this->assertStringContainsString('Keryon Marketplace', $archive->getFromName('README.txt'));
        $archive->close();
        $this->assertSame(hash('sha256', $package->bytes), $package->sha256);
    }

    public function test_package_rejects_traversal_absolute_nested_and_unexpected_entries(): void
    {
        $builder = app(MarketplacePackageBuilder::class);

        foreach (['../source.psd', '/source.psd', 'folder/source.psd', 'source.php', 'C:/source.psd'] as $unsafe) {
            try {
                $builder->assertEntryName($unsafe);
                $this->fail("Unsafe package entry {$unsafe} was accepted.");
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_reference_import_is_realistic_private_idempotent_and_immutable(): void
    {
        $psd = $this->temporary('Weekend Night Club DJ Party Post PSD Template.psd', $this->psdBytes(width: 1260, height: 1260));
        $preview = $this->temporary('preview.png', $this->pngBytes());
        $importer = app(MarketplaceReferenceProductImporter::class);

        $first = $importer->importSundayService($psd, $preview);
        $second = $importer->importSundayService($psd, $preview);

        $this->assertFalse($first->alreadyImported);
        $this->assertTrue($second->alreadyImported);
        $this->assertSame($first->source->id, $second->source->id);
        $this->assertDatabaseCount('marketplace_items', 1);
        $this->assertDatabaseCount('marketplace_source_versions', 1);
        $this->assertDatabaseCount('marketplace_previews', 2);
        $this->assertSame(MarketplaceAccessType::FREE, $first->item->access_type);
        $this->assertSame(MarketplacePublicationStatus::DRAFT, $first->item->publication_status);
        $this->assertSame(MarketplaceRightsStatus::PENDING, $first->source->rights_status);
        $this->assertSame(MarketplaceSourceAvailability::DRAFT, $first->source->availability_status);
        $this->assertSame($first->inspection->sha256, data_get($first->source->compatibility_metadata, 'source_psd.sha256'));
        $this->assertSame('pending_publisher_declaration', data_get($first->source->font_metadata, 'declaration_status'));
        $this->assertFalse(data_get($first->source->font_metadata, 'font_files_bundled'));
        $this->assertSame('pending_publisher_declaration', data_get($first->source->licensing_metadata, 'status'));
        Storage::disk('marketplace')->assertExists($first->source->getRawOriginal('storage_key'));
        Storage::disk('public')->assertMissing($first->source->getRawOriginal('storage_key'));
        $this->assertCount(2, MarketplacePreview::query()->get());

        $this->verifyMarketplaceRights($first->source);
        $third = $importer->importSundayService($psd, $preview);
        $this->assertTrue($third->alreadyImported);
        $this->assertSame(MarketplaceRightsStatus::VERIFIED, $third->source->rights_status);
        $this->assertSame('test-platform-operator', $third->source->rights_verified_by_reference);

        $this->expectException(LogicException::class);
        $first->source->forceFill(['sha256' => str_repeat('a', 64)])->save();
    }

    private function psdBytes(int $width = 100, int $height = 100): string
    {
        return '8BPS'.pack('n', 1).str_repeat("\0", 6).pack('nNNnn', 3, $height, $width, 8, 3).str_repeat("\0", 128);
    }

    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(640, 640);
        $background = imagecolorallocate($image, 20, 40, 80);
        imagefill($image, 0, 0, $background);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return is_string($bytes) ? $bytes : '';
    }

    private function temporary(string $name, string $bytes): string
    {
        $directory = sys_get_temp_dir().'/keryon-marketplace-tests-'.bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);
        $path = $directory.'/'.$name;
        file_put_contents($path, $bytes);
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
