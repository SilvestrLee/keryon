<?php

namespace App\Marketplace\Ingestion;

use Illuminate\Validation\ValidationException;
use ZipArchive;

class MarketplacePackageBuilder
{
    private const ALLOWED_EXTENSIONS = ['psd', 'txt'];

    public function build(
        MarketplacePsdInspection $source,
        string $readme,
        string $psdEntry = 'Sunday-Service.psd',
        string $downloadFilename = 'Keryon-Sunday-Service.zip',
    ): MarketplaceBuiltPackage {
        $this->assertEntryName($psdEntry);
        $this->assertEntryName('README.txt');

        if ($readme === '') {
            throw $this->invalid('The Marketplace package README cannot be empty.');
        }

        $temporary = tempnam(sys_get_temp_dir(), 'keryon-marketplace-');

        if ($temporary === false) {
            throw $this->invalid('A temporary Marketplace package could not be created.');
        }

        try {
            $archive = new ZipArchive;

            if ($archive->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true
                || ! $archive->addFile($source->path, $psdEntry)
                || ! $archive->addFromString('README.txt', $readme)) {
                throw $this->invalid('The Marketplace package could not be constructed.');
            }

            $archive->setMtimeName($psdEntry, 315532800);
            $archive->setMtimeName('README.txt', 315532800);
            $archive->close();
            $this->inspectArchive($temporary);
            $bytes = file_get_contents($temporary);

            if (! is_string($bytes) || $bytes === '') {
                throw $this->invalid('The Marketplace package could not be read after construction.');
            }

            return new MarketplaceBuiltPackage($bytes, basename($downloadFilename), hash('sha256', $bytes), strlen($bytes));
        } finally {
            @unlink($temporary);
        }
    }

    public function assertEntryName(string $name): void
    {
        $normalized = str_replace('\\', '/', $name);
        $extension = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));

        if ($normalized === ''
            || str_starts_with($normalized, '/')
            || preg_match('/^[a-zA-Z]:\//', $normalized) === 1
            || in_array('..', explode('/', $normalized), true)
            || basename($normalized) !== $normalized
            || ! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw $this->invalid('Marketplace package entries must be safe allowlisted root files.');
        }
    }

    private function inspectArchive(string $path): void
    {
        $archive = new ZipArchive;

        if ($archive->open($path) !== true || $archive->numFiles !== 2) {
            throw $this->invalid('The Marketplace package does not contain the expected files.');
        }

        $total = 0;

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $stat = $archive->statIndex($index);

            if (! is_array($stat)) {
                throw $this->invalid('A Marketplace package entry could not be inspected.');
            }

            $this->assertEntryName($stat['name']);
            $total += (int) $stat['size'];

            if ((int) $stat['size'] > (int) config('marketplace.max_psd_bytes', 268_435_456) + 65_536
                || ((int) $stat['comp_size'] > 0 && (int) $stat['size'] / (int) $stat['comp_size'] > 200)) {
                throw $this->invalid('A Marketplace package entry exceeds safe expansion limits.');
            }
        }

        $archive->close();

        if ($total > (int) config('marketplace.max_psd_bytes', 268_435_456) + 65_536) {
            throw $this->invalid('The Marketplace package exceeds safe uncompressed limits.');
        }
    }

    private function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['package' => $message]);
    }
}
