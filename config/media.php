<?php

return [
    'private_disk' => env('MEDIA_PRIVATE_DISK', 'media-private'),
    'public_disk' => env('MEDIA_PUBLIC_DISK', 'media-public'),
    'rendition_cleanup_grace_hours' => (int) env('MEDIA_RENDITION_CLEANUP_GRACE_HOURS', 24),
];
