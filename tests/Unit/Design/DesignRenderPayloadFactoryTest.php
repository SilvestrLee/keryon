<?php

namespace Tests\Unit\Design;

use App\Design\Rendering\DesignRenderPayloadFactory;
use App\Design\Rendering\DesignRenderingContext;
use App\Design\Rendering\Exceptions\DesignRendererException;
use App\Design\Rendering\PlaywrightDesignRenderer;
use App\Design\Rendering\ResolvedDesignMedia;
use App\Enums\DesignOutputFormat;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\TestCase;

class DesignRenderPayloadFactoryTest extends TestCase
{
    public function test_payload_contains_only_bounded_renderer_values_and_resolved_media_bytes(): void
    {
        Storage::fake('render-input');
        Storage::disk('render-input')->put('tenant-owned/background.png', 'png-bytes');
        $context = new DesignRenderingContext(7, 3, 'Private name excluded', 'sunday-modern-reference', 1, 'default', ['title' => '<script>alert(1)</script>', 'date' => '2026-08-23', 'time' => '09:30'], $this->brand(), ['background' => new ResolvedDesignMedia(9, 'render-input', 'tenant-owned/background.png', 'image/png', 1080, 1080)], null, null);

        $payload = app(DesignRenderPayloadFactory::class)->make($context, DesignOutputFormat::SQUARE);

        $this->assertSame(['templateKey', 'templateVersion', 'variant', 'format', 'identity', 'slots', 'brand', 'media'], array_keys($payload));
        $this->assertSame(base64_encode('png-bytes'), $payload['media']['background']['base64']);
        $this->assertArrayNotHasKey('designId', $payload);
        $this->assertArrayNotHasKey('churchId', $payload);
        $this->assertSame('Private name excluded', $payload['identity']['churchName']);
        $this->assertStringNotContainsString('tenant-owned', json_encode($payload));
    }

    public function test_unsupported_media_never_reaches_node(): void
    {
        $context = new DesignRenderingContext(7, 3, 'Church', 'sunday-modern-reference', 1, 'default', ['title' => 'Sunday', 'date' => '2026-08-23', 'time' => '09:30'], $this->brand(), ['background' => new ResolvedDesignMedia(9, 'public', 'asset.svg', 'image/svg+xml', 1080, 1080)], null, null);

        $this->expectException(LogicException::class);
        app(DesignRenderPayloadFactory::class)->make($context, DesignOutputFormat::SQUARE);
    }

    public function test_missing_local_runtime_fails_with_bounded_infrastructure_code(): void
    {
        config(['design-renderer.node_binary' => '/definitely/unavailable/keryon-node']);
        $context = new DesignRenderingContext(7, 3, 'Church', 'sunday-modern-reference', 1, 'default', ['title' => 'Sunday', 'date' => '2026-08-23', 'time' => '09:30'], $this->brand(), [], null, null);

        try {
            app(PlaywrightDesignRenderer::class)->render($context, DesignOutputFormat::SQUARE);
            $this->fail('An unavailable renderer runtime was accepted.');
        } catch (DesignRendererException $exception) {
            $this->assertSame('renderer_runtime_unavailable', $exception->failureCode);
            $this->assertStringNotContainsString('/definitely/', $exception->getMessage());
        }
    }

    private function brand(): array
    {
        return ['background' => '#173F35', 'primary_text' => '#FFFFFF', 'emphasis' => '#F3C969', 'accent' => '#F3C969', 'cta_background' => '#F3C969', 'cta_text' => '#111827', 'heading_font' => 'playfair_display', 'body_font' => 'inter'];
    }
}
