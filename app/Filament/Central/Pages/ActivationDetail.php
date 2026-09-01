<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Platform\Read\PlatformActivationQuery;
use Filament\Pages\Page;

class ActivationDetail extends Page
{
    use InteractsWithPlatformWorkspace;

    protected string $view = 'filament.central.pages.detail';

    protected static ?string $slug = 'activations/{record}';

    protected static bool $shouldRegisterNavigation = false;

    public int $record;

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::ActivationsView;
    }

    public function item()
    {
        return app(PlatformActivationQuery::class)->find($this->record);
    }

    public function sections($r): array
    {
        return ['Activation' => ['UUID' => $r->uuid, 'Church' => $r->churchName, 'Status' => $r->status, 'Primary email' => $r->primaryEmail, 'Country' => $r->country ?? 'Not set'], 'Commercial' => ['Market' => $r->market ?? 'Unassigned', 'Plan version' => $r->planVersion ?? 'Unassigned', 'Billing interval' => $r->billingInterval, 'Payer intent' => $r->payerType], 'Timeline' => ['Created' => $r->createdAt, 'Sent' => $r->sentAt ?? 'Not sent', 'Expires' => $r->expiresAt ?? 'None', 'Accepted' => $r->acceptedAt ?? 'Not accepted', 'Revoked' => $r->revokedAt ?? 'Not revoked'], 'Delivery' => ['Status' => $r->deliveryStatus ?? 'Not requested', 'Attempts' => (string) $r->deliveryAttempts, 'Failure' => $r->deliveryFailure ?? 'None'], 'Evidence' => ['Origin' => $r->origin, 'Operator reference' => $r->operatorReference, 'Terms version' => $r->termsVersion ?? 'None', 'Privacy version' => $r->privacyVersion ?? 'None']];
    }
}
