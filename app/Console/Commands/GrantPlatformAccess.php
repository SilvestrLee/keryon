<?php

namespace App\Console\Commands;

use App\Enums\PlatformAuditReasonCategory;
use App\Enums\PlatformRole;
use App\Models\PlatformMembership;
use App\Models\User;
use App\Platform\PlatformStaffService;
use Illuminate\Console\Command;

class GrantPlatformAccess extends Command
{
    protected $signature = 'platform:grant-access
        {email : Existing verified User email}
        {role : administrator, operations, support, commercial, or trust_security}
        {--origin= : Explicit provisioning origin}
        {--operator= : Explicit operator reference}
        {--authorization= : One-time production bootstrap authorization}';

    protected $description = 'Grant bounded Keryon Central access to an existing verified User';

    public function handle(PlatformStaffService $staff): int
    {
        $origin = trim((string) $this->option('origin'));
        $operator = trim((string) $this->option('operator'));
        if ($origin === '' || $operator === '') {
            $this->components->error('Explicit --origin and --operator values are required.');

            return self::FAILURE;
        }
        if (app()->environment('production')) {
            $expected = (string) env('KERYON_PLATFORM_BOOTSTRAP_TOKEN');
            $provided = (string) $this->option('authorization');
            if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided) || PlatformMembership::query()->exists()) {
                $this->components->error('Production platform bootstrap is unavailable without valid one-time authorization and an empty platform membership registry.');

                return self::FAILURE;
            }
        }
        $role = PlatformRole::tryFrom((string) $this->argument('role'));
        if ($role === null) {
            $this->components->error('Unsupported PlatformRole.');

            return self::FAILURE;
        }
        $user = User::query()->whereRaw('lower(email) = ?', [strtolower((string) $this->argument('email'))])->first();
        if ($user === null) {
            $this->components->error('No existing User matches that email.');

            return self::FAILURE;
        }

        try {
            $membership = $staff->create(
                $user,
                $role,
                null,
                PlatformAuditReasonCategory::PLATFORM_ADMINISTRATION,
                "Bootstrap origin: {$origin}; operator: {$operator}",
            );
            $this->components->info("Platform access granted as {$membership->role->value}.");

            return self::SUCCESS;
        } catch (\DomainException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
