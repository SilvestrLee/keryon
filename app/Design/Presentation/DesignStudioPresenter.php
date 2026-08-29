<?php

namespace App\Design\Presentation;

use App\Design\Templates\DesignSlot;
use App\Design\Templates\DesignTemplateDefinition;
use App\Enums\DesignOutputFormat;
use App\Enums\DesignOutputStatus;
use App\Enums\DesignPurpose;
use App\Models\Design;

class DesignStudioPresenter
{
    /** @return array<string, array{label: string, description: string}> */
    public function purposes(): array
    {
        return [
            DesignPurpose::SERVICE->value => ['label' => 'Sunday service', 'description' => 'Invite your church into worship and community.'],
            DesignPurpose::ANNOUNCEMENT->value => ['label' => 'Announcement', 'description' => 'Make an important church update clear.'],
            DesignPurpose::SCRIPTURE->value => ['label' => 'Scripture', 'description' => 'Share a passage with thoughtful visual emphasis.'],
            DesignPurpose::QUOTE->value => ['label' => 'Quote', 'description' => 'Highlight a memorable line or teaching moment.'],
            DesignPurpose::CAMPAIGN->value => ['label' => 'Campaign', 'description' => 'Create a visual connected to a communication plan.'],
        ];
    }

    /** @return array{label: string, use: string, dimensions: string} */
    public function format(DesignOutputFormat $format): array
    {
        return match ($format) {
            DesignOutputFormat::SQUARE => ['label' => 'Square', 'use' => 'Feed posts', 'dimensions' => '1080 × 1080'],
            DesignOutputFormat::PORTRAIT => ['label' => 'Portrait', 'use' => 'Portrait social posts', 'dimensions' => '1080 × 1350'],
            DesignOutputFormat::STORY => ['label' => 'Story', 'use' => 'Stories and status', 'dimensions' => '1080 × 1920'],
        };
    }

    public function slotInputType(DesignSlot $slot): string
    {
        return match ($slot->type->value) {
            'date' => 'date',
            'time' => 'time',
            'long_text' => 'textarea',
            default => 'text',
        };
    }

    /** @return array{label: string, tone: string, description: string} */
    public function state(Design $design): array
    {
        $outputs = $design->outputs;

        if ($design->state->value === 'approved') {
            return ['label' => 'Approved', 'tone' => 'approved', 'description' => 'Available in Media'];
        }

        if ($outputs->contains(fn ($output) => $output->status === DesignOutputStatus::FAILED)) {
            return ['label' => 'Needs attention', 'tone' => 'attention', 'description' => 'One or more formats need another try'];
        }

        if ($outputs->isNotEmpty() && $outputs->every(fn ($output) => $output->isRendered())) {
            return ['label' => 'Ready for review', 'tone' => 'ready', 'description' => 'All requested formats are ready'];
        }

        return ['label' => 'Creating', 'tone' => 'creating', 'description' => 'Preparing requested formats'];
    }

    public function templateDescription(DesignTemplateDefinition $template): string
    {
        return $template->family === 'sunday-modern'
            ? 'A composed service announcement with confident type and generous space.'
            : 'An approved Keryon communication template.';
    }
}
