<?php

namespace App\Platform\Read;

use App\Enums\PlatformCapability;
use App\Platform\Read\Dto\PlatformProviderStatus;
use App\Trust\Ai\AiProviderRegistry;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PlatformProviderStatusQuery extends PlatformReadQuery
{
    /** @return list<PlatformProviderStatus> */
    public function all(): array
    {
        $this->authorize(PlatformCapability::ProvidersView);
        $anthropic = app(AiProviderRegistry::class)->provider('anthropic') ?? [];
        $paystack = (array) config('billing.providers.paystack', []);
        $mailer = (string) config('invitation-delivery.mailer', 'array');
        $dns = (string) config('public-website.custom_domains.dns_resolver', 'unavailable');
        $tls = (string) config('public-website.custom_domains.provisioner', 'unavailable');
        $database = $this->databaseStatus();

        return [
            new PlatformProviderStatus('anthropic', 'Anthropic', 'AI governance', app()->environment(), ! empty($anthropic['account_configuration_verified_at']), (string) ($anthropic['status'] ?? 'unregistered'), ($anthropic['status'] ?? null) === 'approved' ? 'Approved' : 'Production processing blocked', isset($anthropic['reviewed_at']) ? (string) $anthropic['reviewed_at'] : null, ($anthropic['status'] ?? null) === 'approved' ? [] : ['Provider governance approval is incomplete']),
            new PlatformProviderStatus('paystack', 'Paystack', 'Payments', (string) ($paystack['environment'] ?? 'unknown'), (bool) ($paystack['enabled'] ?? false) && filled($paystack['public_key'] ?? null) && filled($paystack['secret_key'] ?? null), (string) ($paystack['governance_status'] ?? 'unknown'), ($paystack['governance_status'] ?? null) === 'sandbox_only' ? 'Sandbox only' : 'Not proven for production', null, ['Production approval is not established']),
            new PlatformProviderStatus('email', 'Invitation Email', 'Delivery', app()->environment(), ! in_array($mailer, ['array', 'log'], true), 'foundation_ready', in_array($mailer, ['array', 'log'], true) ? 'Production provider unavailable' : 'Configured; runtime health unknown', null, in_array($mailer, ['array', 'log'], true) ? ['Current mail transport is not production delivery'] : ['No delivery heartbeat exists']),
            new PlatformProviderStatus('dns', 'DNS resolver', 'Domains', app()->environment(), $dns !== 'unavailable', 'application_ready', $dns === 'unavailable' ? 'Production adapter unavailable' : 'Configured; runtime health unknown', null, $dns === 'unavailable' ? ['DNS resolver adapter is unavailable'] : []),
            new PlatformProviderStatus('tls', 'TLS provisioner', 'Domains', app()->environment(), $tls !== 'unavailable', 'application_ready', $tls === 'unavailable' ? 'Production adapter unavailable' : 'Configured; runtime health unknown', null, $tls === 'unavailable' ? ['TLS provisioner adapter is unavailable'] : []),
            new PlatformProviderStatus('queue', 'Queue', 'Infrastructure', app()->environment(), filled(config('queue.default')), 'configured', 'Worker health unknown', null, ['No worker heartbeat evidence exists']),
            new PlatformProviderStatus('scheduler', 'Scheduler', 'Infrastructure', app()->environment(), true, 'configured', 'Runtime health unknown', null, ['No scheduler heartbeat evidence exists']),
            new PlatformProviderStatus('storage', 'Storage', 'Infrastructure', app()->environment(), filled(config('filesystems.default')), 'configured', 'Configured; runtime health unknown', null, ['Customer objects are not probed']),
            new PlatformProviderStatus('renderer', 'Design renderer', 'Infrastructure', app()->environment(), filled(config('design-renderer.node_binary')), 'configured', 'Runtime health unknown', null, ['No non-customer renderer heartbeat exists']),
            $database,
        ];
    }

    private function databaseStatus(): PlatformProviderStatus
    {
        try {
            $connection = DB::connection();
            $driver = $connection->getDriverName();
            $version = $driver === 'sqlite' ? (string) $connection->selectOne('select sqlite_version() as version')->version : (string) $connection->selectOne('select version() as version')->version;

            return new PlatformProviderStatus('database', 'Database', 'Infrastructure', $driver, true, 'connected', "Connected ({$driver} {$version})", now()->toIso8601String(), []);
        } catch (Throwable) {
            return new PlatformProviderStatus('database', 'Database', 'Infrastructure', 'unknown', false, 'unavailable', 'Unavailable', now()->toIso8601String(), ['Database connectivity failed']);
        }
    }
}
