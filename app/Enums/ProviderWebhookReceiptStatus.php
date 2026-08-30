<?php

namespace App\Enums;

enum ProviderWebhookReceiptStatus: string
{
    case RECEIVED = 'received';
    case PROCESSING = 'processing';
    case PROCESSED = 'processed';
    case IGNORED = 'ignored';
    case FAILED = 'failed';
}
