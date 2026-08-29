<?php

namespace App\Enums;

enum PublicationAiReviewStatus: string
{
    case Unknown = 'unknown';
    case NotApplicable = 'not_applicable';
    case HumanReviewed = 'human_reviewed';
    case Unreviewed = 'unreviewed';
}
