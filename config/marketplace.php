<?php

return [
    'source_disk' => env('MARKETPLACE_SOURCE_DISK', 'marketplace'),
    'preview_disk' => env('MARKETPLACE_PREVIEW_DISK', 'marketplace'),
    'max_psd_bytes' => (int) env('MARKETPLACE_MAX_PSD_BYTES', 268_435_456),
];
