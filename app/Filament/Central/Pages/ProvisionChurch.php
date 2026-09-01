<?php

namespace App\Filament\Central\Pages;

use App\Enums\BillingAccountOwnerType;
use App\Enums\BillingInterval;
use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithGovernedOperation;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Onboarding\ProvisionChurchData;
use App\Platform\Operations\PlatformProvisionChurch;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class ProvisionChurch extends Page
{
    use InteractsWithGovernedOperation, InteractsWithPlatformWorkspace;

    protected string $view = 'filament.central.pages.provision-church';

    protected static ?string $title = 'Provision Church';

    protected static bool $shouldRegisterNavigation = false;

    public string $churchName = '';

    public string $requestedSlug = '';

    public string $country = '';

    public string $timezone = 'Africa/Lagos';

    public string $primaryEmail = '';

    public string $billingInterval = 'monthly';

    public string $payerType = 'church';

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::PlatformChurchProvision;
    }

    public function mount(): void
    {
        $this->prepareOperation('provision');
        $this->reason = 'platform_administration';
    }

    public function provision(PlatformProvisionChurch $operation): void
    {
        $reason = $this->authorizeOperation();
        $data = $this->validate(['churchName' => ['required', 'string', 'max:255'], 'requestedSlug' => ['nullable', 'string', 'max:255'], 'country' => ['required', 'string', 'size:2'], 'timezone' => ['required', 'timezone'], 'primaryEmail' => ['required', 'email'], 'billingInterval' => ['required', 'in:monthly,annual'], 'payerType' => ['required', 'in:church']]);
        $result = $operation->execute(new ProvisionChurchData('platform-membership:'.$this->platformMembership()->id, 'central', $this->correlationId, $data['churchName'], $data['requestedSlug'] ?: null, strtoupper($data['country']), $data['timezone'], $data['primaryEmail'], BillingInterval::from($data['billingInterval']), BillingAccountOwnerType::CHURCH), $reason, $this->reasonNote);
        Notification::make()->success()->title($result->message)->send();
        $this->redirect(ActivationDetail::getUrl(['record' => $result->targetId], panel: 'central'));
    }

    public function regenerateIdempotency(): void
    {
        $this->correlationId = (string) Str::uuid();
    }
}
