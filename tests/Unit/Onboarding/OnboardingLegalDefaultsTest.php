<?php

namespace Tests\Unit\Onboarding;

use PHPUnit\Framework\TestCase;

/**
 * Evaluates config/onboarding.php's env-gated default resolution in a fresh
 * child process with a fully controlled environment, since Laravel's runtime
 * config() overrides used elsewhere in the suite cannot exercise the env()
 * default expression itself. Mirrors the accepted config/staff.php pattern:
 * a development fixture may resolve only under APP_ENV local/testing; every
 * other environment must resolve to null when the identifier is unset.
 */
class OnboardingLegalDefaultsTest extends TestCase
{
    public function test_production_environment_without_explicit_versions_resolves_to_null(): void
    {
        $config = $this->resolveConfig('production');

        $this->assertNull($config['legal']['terms_version']);
        $this->assertNull($config['legal']['privacy_version']);
    }

    public function test_staging_environment_without_explicit_versions_resolves_to_null(): void
    {
        $config = $this->resolveConfig('staging');

        $this->assertNull($config['legal']['terms_version']);
        $this->assertNull($config['legal']['privacy_version']);
    }

    public function test_local_environment_without_explicit_versions_retains_development_fixture(): void
    {
        $config = $this->resolveConfig('local');

        $this->assertSame('development-terms-v1', $config['legal']['terms_version']);
        $this->assertSame('development-privacy-v1', $config['legal']['privacy_version']);
    }

    public function test_testing_environment_without_explicit_versions_retains_development_fixture(): void
    {
        $config = $this->resolveConfig('testing');

        $this->assertSame('development-terms-v1', $config['legal']['terms_version']);
        $this->assertSame('development-privacy-v1', $config['legal']['privacy_version']);
    }

    public function test_explicit_env_var_overrides_win_regardless_of_app_env(): void
    {
        $config = $this->resolveConfig('production', 'governed-terms-v9', 'governed-privacy-v9');

        $this->assertSame('governed-terms-v9', $config['legal']['terms_version']);
        $this->assertSame('governed-privacy-v9', $config['legal']['privacy_version']);
    }

    /**
     * @return array{legal: array{terms_version: ?string, privacy_version: ?string}}
     */
    private function resolveConfig(string $appEnv, ?string $termsVersion = null, ?string $privacyVersion = null): array
    {
        $configPath = dirname(__DIR__, 3).'/config/onboarding.php';
        $autoloadPath = dirname(__DIR__, 3).'/vendor/autoload.php';
        $this->assertFileExists($configPath);
        $this->assertFileExists($autoloadPath);

        $env = ['PATH' => getenv('PATH') ?: '', 'APP_ENV' => $appEnv];
        if ($termsVersion !== null) {
            $env['KERYON_TERMS_VERSION'] = $termsVersion;
        }
        if ($privacyVersion !== null) {
            $env['KERYON_PRIVACY_VERSION'] = $privacyVersion;
        }

        // env() is a Laravel/illuminate-support helper, not a PHP builtin —
        // the child process needs Composer's autoloader (which registers
        // illuminate/support's helpers.php) before it can call it.
        $code = 'require '.var_export($autoloadPath, true).'; echo json_encode(require '.var_export($configPath, true).');';
        $process = proc_open(
            [PHP_BINARY, '-r', $code],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $env,
        );
        $this->assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, "Child process failed (exit {$exitCode}): {$stderr}");
        $decoded = json_decode($stdout, true);
        $this->assertIsArray($decoded, "Could not decode config output: {$stdout}");

        return $decoded;
    }
}
