<?php

namespace App\Marketplace;

use Illuminate\Validation\ValidationException;

class MarketplaceSourceIntegrity
{
    /** @return array{extension: string, mimeType: string, size: int, sha256: string} */
    public function inspect(string $bytes, string $originalFilename): array
    {
        if ($bytes === '') {
            throw ValidationException::withMessages(['source' => 'A Marketplace source package cannot be empty.']);
        }

        $extension = strtolower(pathinfo(basename($originalFilename), PATHINFO_EXTENSION));
        $mimeType = match ($extension) {
            'psd' => str_starts_with($bytes, '8BPS') ? 'image/vnd.adobe.photoshop' : null,
            'zip' => preg_match('/^PK[\x03\x05\x07][\x04\x06\x08]/', $bytes) === 1 ? 'application/zip' : null,
            default => null,
        };

        if ($mimeType === null) {
            throw ValidationException::withMessages(['source' => 'Marketplace source packages must be signature-valid PSD or ZIP files.']);
        }

        return [
            'extension' => $extension,
            'mimeType' => $mimeType,
            'size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
        ];
    }
}
