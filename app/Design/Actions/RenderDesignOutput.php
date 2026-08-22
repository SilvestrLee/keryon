<?php

namespace App\Design\Actions;

use App\Design\Rendering\DesignRenderer;
use App\Design\Rendering\DesignRenderingContextFactory;
use App\Design\Rendering\Exceptions\DesignRendererException;
use App\Models\DesignOutput;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

class RenderDesignOutput
{
    public function __construct(
        private readonly DesignRenderer $renderer,
        private readonly DesignRenderingContextFactory $contexts,
    ) {}

    public function handle(DesignOutput $output): DesignOutput
    {
        $output->loadMissing('design');

        if (! $output->design || $output->design->church_id !== $output->church_id) {
            throw new LogicException('A Design output requires its same-Church Design.');
        }

        try {
            $rendered = $this->renderer->render($this->contexts->forDesign($output->design), $output->format);
            $uuid = (string) Str::uuid();
            $filename = "design-{$output->format->value}.png";
            $path = "tenants/{$output->church_id}/media/{$uuid}/{$filename}";
            $disk = config('design-renderer.disk', 'public');

            if (! Storage::disk($disk)->put($path, $rendered->bytes)) {
                throw new DesignRendererException('media_storage_failed');
            }

            try {
                $asset = new MediaAsset([
                    'disk' => $disk,
                    'path' => $path,
                    'original_filename' => $filename,
                    'mime_type' => $rendered->mimeType,
                    'size' => strlen($rendered->bytes),
                    'width' => $rendered->width,
                    'height' => $rendered->height,
                    'alt_text' => 'Generated church communication design',
                ]);
                $asset->forceFill(['church_id' => $output->church_id, 'uuid' => $uuid])->save();
                $output->markRendered($asset);
            } catch (\Throwable $exception) {
                Storage::disk($disk)->delete($path);
                throw $exception;
            }
        } catch (DesignRendererException $exception) {
            $output->markFailed($exception->failureCode);
        }

        return $output->fresh(['mediaAsset']);
    }
}
