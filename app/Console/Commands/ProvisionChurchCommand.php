<?php

namespace App\Console\Commands;

use App\Enums\BillingAccountOwnerType;
use App\Enums\BillingInterval;
use App\Enums\ChurchActivationStatus;
use App\Onboarding\ChurchActivationTokenService;
use App\Onboarding\ProvisionChurch;
use App\Onboarding\ProvisionChurchData;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ProvisionChurchCommand extends Command
{
    protected $signature = 'onboarding:provision-church
        {name} {email} {country} {timezone}
        {--slug=} {--interval=monthly} {--payer=church}
        {--payer-id=} {--operator=local-operator} {--origin=artisan}
        {--idempotency-key=} {--issue-token}';

    protected $description = 'Internally provision an inactive Church and its governed Primary activation';

    public function handle(ProvisionChurch $provision, ChurchActivationTokenService $tokens): int
    {
        $payer = BillingAccountOwnerType::tryFrom((string) $this->option('payer'));
        $interval = BillingInterval::tryFrom((string) $this->option('interval'));
        if (! $payer || ! $interval) {
            $this->components->error('Use a supported payer type and monthly/annual interval.');

            return self::FAILURE;
        }
        $payerId = $this->option('payer-id') ? (int) $this->option('payer-id') : null;
        $result = $provision->execute(new ProvisionChurchData(
            operatorReference: (string) $this->option('operator'), origin: (string) $this->option('origin'),
            idempotencyKey: (string) ($this->option('idempotency-key') ?: Str::uuid()),
            churchName: (string) $this->argument('name'), requestedSlug: $this->option('slug'),
            operatingCountryCode: (string) $this->argument('country'), timezone: (string) $this->argument('timezone'),
            prospectivePrimaryEmail: (string) $this->argument('email'), billingInterval: $interval, payerType: $payer,
            payerOrganizationId: $payer === BillingAccountOwnerType::ORGANIZATION ? $payerId : null,
            payerOrganizationUnitId: $payer === BillingAccountOwnerType::ORGANIZATION_UNIT ? $payerId : null,
        ));
        $this->components->info("Provisioned inactive Church ID [{$result->church->id}] with activation [{$result->activation->uuid}].");
        if ($this->option('issue-token')) {
            if ($result->activation->status !== ChurchActivationStatus::PENDING) {
                $this->components->warn('Commercial review is required; no activation token was issued.');

                return self::SUCCESS;
            }
            $token = $tokens->issue($result->activation);
            $this->components->warn('SENSITIVE LOCAL ACTIVATION LINK — deliver only to the intended Primary:');
            $this->line(route('church-activation.show', ['token' => $token]));
        }

        return self::SUCCESS;
    }
}
