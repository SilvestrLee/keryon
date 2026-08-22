<?php

namespace App\Design\Rendering;

use App\Enums\DesignOutputFormat;

interface DesignRenderer
{
    public function render(DesignRenderingContext $context, DesignOutputFormat $format): RenderedDesignFile;
}
