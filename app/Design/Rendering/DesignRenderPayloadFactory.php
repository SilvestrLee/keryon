<?php

namespace App\Design\Rendering;

use App\Enums\DesignOutputFormat;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use LogicException;

class DesignRenderPayloadFactory
{
    private const ALLOWED_MEDIA_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

    public function __construct(private readonly FilesystemFactory $filesystems) {}

    /** @return array<string, mixed> */
    public function make(DesignRenderingContext $context, DesignOutputFormat $format): array
    {
        $media = [];

        foreach ($context->media as $key => $resolved) {
            if (! in_array($resolved->mimeType, self::ALLOWED_MEDIA_TYPES, true)) {
                throw new LogicException('The selected Design media type is not supported by the renderer.');
            }

            $bytes = $this->filesystems->disk($resolved->disk)->get($resolved->path);

            if (! is_string($bytes) || strlen($bytes) > 15_000_000) {
                throw new LogicException('The selected Design media could not be safely resolved.');
            }

            $media[$key] = ['mimeType' => $resolved->mimeType, 'base64' => base64_encode($bytes)];
        }

        ksort($media);

        return [
            'templateKey' => $context->templateKey,
            'templateVersion' => $context->templateVersion,
            'variant' => $context->variant,
            'format' => $format->value,
            'identity' => ['churchName' => $context->churchName],
            'slots' => $context->inputs,
            'brand' => $context->brand,
            'media' => $media,
        ];
    }
}
