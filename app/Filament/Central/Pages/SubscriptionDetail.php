<?php

namespace App\Filament\Central\Pages;

use App\Enums\PlatformCapability;
use App\Filament\Central\Concerns\InteractsWithPlatformWorkspace;
use App\Platform\Read\PlatformCommercialQuery;
use Filament\Pages\Page;

class SubscriptionDetail extends Page
{
    use InteractsWithPlatformWorkspace;

    protected string $view = 'filament.central.pages.detail';

    protected static ?string $slug = 'subscriptions/{record}';

    protected static bool $shouldRegisterNavigation = false;

    public int $record;

    protected static function requiredCapability(): PlatformCapability
    {
        return PlatformCapability::SubscriptionsView;
    }

    public function item()
    {
        return app(PlatformCommercialQuery::class)->find($this->record);
    }

    public function sections($r): array
    {
        $s = ['Subscription' => ['UUID' => $r->uuid, 'Church' => $r->churchName, 'Status' => $r->status, 'Created' => $r->createdAt], 'Plan and pricing' => ['Plan version' => $r->planVersion ?? 'None', 'Market' => $r->market ?? 'None', 'Price reference' => $r->price ?? 'Restricted', 'Currency' => $r->currency ?? 'Restricted', 'Amount minor' => $r->amountMinor === null ? 'Restricted' : (string) $r->amountMinor], 'Period' => ['Trial started' => $r->trialStartedAt ?? 'None', 'Trial ends' => $r->trialEndsAt ?? 'None', 'Period start' => $r->periodStart ?? 'None', 'Period end' => $r->periodEnd ?? 'None', 'Cancel at period end' => $r->cancelAtPeriodEnd ? 'Yes' : 'No']];
        if ($r->payerName) {
            $s['Payer'] = ['Type' => $r->payerType ?? 'Unknown', 'Name' => $r->payerName, 'Billing email' => $r->billingEmail ?? 'Not set'];
        }if ($r->invoices) {
            $s['Recent invoices'] = collect($r->invoices)->mapWithKeys(fn ($v) => [$v['invoice_number'] ?? $v['uuid'] => $v['status'].' · '.$v['currency'].' '.$v['total_minor']])->all();
        }if ($r->payments) {
            $s['Recent payments'] = collect($r->payments)->mapWithKeys(fn ($v) => [$v['uuid'] => $v['status'].' · '.$v['currency'].' '.$v['amount_minor']])->all();
        }

        return $s;
    }
}
