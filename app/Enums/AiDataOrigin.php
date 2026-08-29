<?php

namespace App\Enums;

enum AiDataOrigin: string
{
    case FaithFlowSource = 'faithflow.source';
    case FaithFlowAnalysis = 'faithflow.analysis';
    case Care = 'care';
}
