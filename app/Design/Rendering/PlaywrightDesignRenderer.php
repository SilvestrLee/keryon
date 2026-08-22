<?php

namespace App\Design\Rendering;

use App\Design\Rendering\Exceptions\DesignRendererException;
use App\Enums\DesignOutputFormat;
use JsonException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class PlaywrightDesignRenderer implements DesignRenderer
{
    public function __construct(private readonly DesignRenderPayloadFactory $payloads) {}

    public function render(DesignRenderingContext $context, DesignOutputFormat $format): RenderedDesignFile
    {
        $process = new Process(
            [config('design-renderer.node_binary', 'node'), base_path('renderer/render.mjs')],
            base_path(),
            timeout: (float) config('design-renderer.timeout_seconds', 35),
        );

        try {
            $process->setInput(json_encode($this->payloads->make($context, $format), JSON_THROW_ON_ERROR));
            $process->run();

            if (! $process->isSuccessful() && trim($process->getOutput()) === '') {
                throw new DesignRendererException('renderer_runtime_unavailable');
            }

            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        } catch (ProcessTimedOutException) {
            throw new DesignRendererException('renderer_timeout');
        } catch (DesignRendererException $exception) {
            throw $exception;
        } catch (JsonException) {
            throw new DesignRendererException('renderer_protocol_failed');
        } catch (\Throwable) {
            throw new DesignRendererException('renderer_runtime_unavailable');
        }

        if (! $process->isSuccessful() || ! is_array($result) || ($result['ok'] ?? false) !== true) {
            $code = is_array($result) && is_string($result['code'] ?? null)
                ? $result['code']
                : 'renderer_runtime_failed';
            throw new DesignRendererException(preg_match('/^[a-z][a-z0-9_]{2,63}$/', $code) ? $code : 'renderer_runtime_failed');
        }

        $bytes = base64_decode($result['png'] ?? '', true);

        if (! is_string($bytes) || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            throw new DesignRendererException('renderer_protocol_failed');
        }

        if (($result['width'] ?? null) !== $format->width() || ($result['height'] ?? null) !== $format->height()) {
            throw new DesignRendererException('renderer_dimension_mismatch');
        }

        return new RenderedDesignFile($bytes, 'image/png', 'png', $format->width(), $format->height());
    }
}
