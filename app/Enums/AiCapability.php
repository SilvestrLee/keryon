<?php

namespace App\Enums;

enum AiCapability: string
{
    case FaithFlowAnalysis = 'faithflow.analysis';
    case FaithFlowGeneration = 'faithflow.generation';
}
