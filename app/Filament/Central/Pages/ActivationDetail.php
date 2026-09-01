<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithGovernedOperation;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Platform\Operations\PlatformResendActivation;
use App\Platform\Operations\PlatformRevokeActivation;
use App\Platform\Read\PlatformActivationQuery;
use App\Support\PlatformContext;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ActivationDetail extends Page
{
    use InteractsWithGovernedOperation, InteractsWithPlatformWorkspace;

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

    public function canResend(): bool
    {
        return $this->item()->status === 'pending' && app(PlatformContext::class)->hasCapability(PlatformCapability::PlatformActivationResend);
    }

    public function canRevoke(): bool
    {
        return in_array($this->item()->status, ['pending', 'commercial_review'], true) && app(PlatformContext::class)->hasCapability(PlatformCapability::PlatformActivationRevoke);
    }

    public function operationView(): string
    {
        return 'filament.central.pages.partials.activation-actions';
    }

    public function runOperation(PlatformResendActivation $resend, PlatformRevokeActivation $revoke): void
    {
        abort_unless(($this->operation === 'resend' && $this->canResend()) || ($this->operation === 'revoke' && $this->canRevoke()), 403);
        $reason = $this->authorizeOperation();
        try {
            $result = $this->operation === 'resend' ? $resend->execute($this->record, $reason, $this->reasonNote, $this->correlationId) : $revoke->execute($this->record, $reason, $this->reasonNote, $this->correlationId);
            Notification::make()->success()->title($result->message)->send();
            $this->cancelOperation();
        } catch (DomainException $exception) {
            $this->addError('operation', $exception->getMessage());
        }
    }
}
