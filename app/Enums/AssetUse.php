<?php

namespace App\Enums;

enum AssetUse: string
{
    case Store = 'store';
    case InternalUse = 'internal_use';
    case Publish = 'publish';
    case AiProcess = 'ai_process';
    case Reference = 'reference';
    case Train = 'train';
    case CommercialUse = 'commercial_use';
    case Redistribute = 'redistribute';
}
