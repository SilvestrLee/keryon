<?php

namespace App\Marketplace\Ingestion;

use Illuminate\Validation\ValidationException;

class MarketplacePsdInspector
{
    public function inspect(string $path): MarketplacePsdInspection
    {
        $realPath = realpath($path);

        if ($realPath === false || ! is_file($realPath) || ! is_readable($realPath)) {
            throw $this->invalid('The Marketplace PSD source does not exist or is not readable.');
        }

        if (strtolower(pathinfo($realPath, PATHINFO_EXTENSION)) !== 'psd') {
            throw $this->invalid('The Marketplace source must use the .psd extension.');
        }

        $size = filesize($realPath);
        $maximum = (int) config('marketplace.max_psd_bytes', 268_435_456);

        if (! is_int($size) || $size < 27) {
            throw $this->invalid('The Marketplace PSD source is empty or incomplete.');
        }

        if ($size > $maximum) {
            throw $this->invalid('The Marketplace PSD source exceeds the configured size limit.');
        }

        $handle = fopen($realPath, 'rb');
        $header = $handle === false ? false : fread($handle, 26);

        if (is_resource($handle)) {
            fclose($handle);
        }

        if (! is_string($header) || strlen($header) !== 26 || substr($header, 0, 4) !== '8BPS') {
            throw $this->invalid('The Marketplace source does not have a valid PSD signature.');
        }

        $values = unpack('nversion/a6reserved/nchannels/Nheight/Nwidth/ndepth/ncolorMode', substr($header, 4));

        if (! is_array($values)
            || ! in_array($values['version'], [1, 2], true)
            || $values['reserved'] !== str_repeat("\0", 6)
            || $values['channels'] < 1
            || $values['channels'] > 56
            || $values['width'] < 1
            || $values['height'] < 1
            || ! in_array($values['depth'], [1, 8, 16, 32], true)
            || $values['colorMode'] > 15) {
            throw $this->invalid('The Marketplace source has an invalid PSD header.');
        }

        $sha256 = hash_file('sha256', $realPath);

        if (! is_string($sha256)) {
            throw $this->invalid('The Marketplace PSD checksum could not be calculated.');
        }

        return new MarketplacePsdInspection(
            path: $realPath,
            originalFilename: basename($realPath),
            size: $size,
            sha256: $sha256,
            version: $values['version'],
            channels: $values['channels'],
            width: $values['width'],
            height: $values['height'],
            depth: $values['depth'],
            colorMode: $values['colorMode'],
        );
    }

    private function invalid(string $message): ValidationException
    {
        return ValidationException::withMessages(['source' => $message]);
    }
}
