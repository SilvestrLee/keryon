<?php

namespace App\Filament\Central\Concerns;

use App\Enums\PlatformAuditReasonCategory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

trait InteractsWithGovernedOperation
{
    public string $operation = '';

    public string $reason = 'customer_request';

    public string $reasonNote = '';

    public string $password = '';

    public string $correlationId = '';

    public function prepareOperation(string $operation): void
    {
        $this->operation = $operation;
        $this->reason = 'customer_request';
        $this->reasonNote = '';
        $this->password = '';
        $this->correlationId = (string) Str::uuid();
        $this->resetErrorBag('operation');
    }

    public function cancelOperation(): void
    {
        $this->reset(['operation', 'reasonNote', 'password', 'correlationId']);
        $this->resetErrorBag('operation');
    }

    /** @return array<string, array<int, mixed>> */
    protected function operationRules(): array
    {
        return [
            'reason' => ['required', Rule::enum(PlatformAuditReasonCategory::class)],
            'reasonNote' => ['required', 'string', 'max:500'],
            'password' => ['required', 'string'],
            'correlationId' => ['required', 'uuid'],
        ];
    }

    protected function authorizeOperation(): PlatformAuditReasonCategory
    {
        $data = $this->validate($this->operationRules());
        abort_unless(Hash::check($data['password'], auth()->user()->password), 403, 'Password confirmation failed.');

        return PlatformAuditReasonCategory::from($data['reason']);
    }

    /** @return array<string, string> */
    public function reasonOptions(): array
    {
        return collect(PlatformAuditReasonCategory::cases())->mapWithKeys(fn ($reason) => [$reason->value => str($reason->value)->replace('_', ' ')->headline()->toString()])->all();
    }
}
