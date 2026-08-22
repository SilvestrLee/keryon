<?php

namespace Tests\Feature\Async;

use App\Enums\ChurchRole;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\User;
use App\Support\Exceptions\UntrustedTenantExecutionException;
use App\Support\TenantContext;
use App\Support\TenantExecutionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use RuntimeException;
use Tests\TestCase;

/**
 * K-ASYNC-001 §6/§9-§13/§32 — TenantContext::capture()/runFor(), exercised
 * directly (independent of any Job), matching the directive's required
 * "Context capture" / "Restoration" / "Tenant isolation" / "Actor edge
 * cases" / "Cleanup" test categories.
 */
class TenantContextBackgroundExecutionTest extends TestCase
{
    use RefreshDatabase;

    // --- Context capture (§10) ---

    public function test_capture_reflects_the_current_trusted_context(): void
    {
        $church = Church::create(['name' => 'Capture Church', 'slug' => 'capture-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        $context = app(TenantContext::class)->capture();

        $this->assertSame($church->id, $context->churchId);
        $this->assertSame($user->id, $context->actorUserId);
    }

    public function test_capture_has_no_parameter_through_which_an_arbitrary_context_could_be_supplied(): void
    {
        $church = Church::create(['name' => 'Capture Church Two', 'slug' => 'capture-church-two']);
        $otherChurch = Church::create(['name' => 'Other Capture Church', 'slug' => 'other-capture-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        User::factory()->forChurch($otherChurch, [ChurchRole::COMMUNICATIONS])->create();

        $this->actingAs($user);

        // capture() takes zero arguments — there is no way to ask it for
        // $otherChurch's context while authenticated as $user.
        $context = app(TenantContext::class)->capture();

        $this->assertSame($church->id, $context->churchId);
        $this->assertNotSame($otherChurch->id, $context->churchId);
    }

    public function test_capture_throws_when_there_is_no_active_context(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->expectException(LogicException::class);

        app(TenantContext::class)->capture();
    }

    // --- Restoration (§11/§12/§13) ---

    public function test_run_for_restores_the_correct_church_and_membership(): void
    {
        $church = Church::create(['name' => 'Restore Church', 'slug' => 'restore-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $context = new TenantExecutionContext($church->id, $user->id);

        $observed = app(TenantContext::class)->runFor($context, function () {
            $tc = app(TenantContext::class);

            return [$tc->currentChurchId(), $tc->currentMembership()?->user_id];
        });

        $this->assertSame([$church->id, $user->id], $observed);
    }

    public function test_run_for_supports_a_system_originated_context_with_no_actor(): void
    {
        $church = Church::create(['name' => 'System Church', 'slug' => 'system-church']);
        $context = new TenantExecutionContext($church->id, null);

        $observed = app(TenantContext::class)->runFor($context, function () {
            $tc = app(TenantContext::class);

            return [$tc->currentChurchId(), $tc->currentMembership(), $tc->hasContext()];
        });

        $this->assertSame($church->id, $observed[0]);
        $this->assertNull($observed[1]);
        $this->assertTrue($observed[2]);
    }

    public function test_run_for_rejects_a_membership_revoked_between_dispatch_and_execution(): void
    {
        $church = Church::create(['name' => 'Revoked Church', 'slug' => 'revoked-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $context = new TenantExecutionContext($church->id, $user->id);

        // Simulates T2 from §10: membership suspended after dispatch.
        ChurchMembership::query()->where('user_id', $user->id)->where('church_id', $church->id)->first()->suspend();

        $this->expectException(UntrustedTenantExecutionException::class);

        app(TenantContext::class)->runFor($context, fn () => null);
    }

    public function test_run_for_rejects_an_inactive_church(): void
    {
        $church = Church::create(['name' => 'Deactivated Church', 'slug' => 'deactivated-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $context = new TenantExecutionContext($church->id, $user->id);

        $church->update(['is_active' => false]);

        $this->expectException(UntrustedTenantExecutionException::class);

        app(TenantContext::class)->runFor($context, fn () => null);
    }

    public function test_run_for_rejects_a_missing_church(): void
    {
        $context = new TenantExecutionContext(999999, null);

        $this->expectException(UntrustedTenantExecutionException::class);

        app(TenantContext::class)->runFor($context, fn () => null);
    }

    public function test_run_for_rejects_a_system_context_whose_church_is_inactive(): void
    {
        $church = Church::create(['name' => 'Deactivated System Church', 'slug' => 'deactivated-system-church', 'is_active' => false]);
        $context = new TenantExecutionContext($church->id, null);

        $this->expectException(UntrustedTenantExecutionException::class);

        app(TenantContext::class)->runFor($context, fn () => null);
    }

    // --- Cleanup (§13) ---

    public function test_run_for_clears_the_restored_context_after_normal_completion(): void
    {
        $church = Church::create(['name' => 'Cleanup Church', 'slug' => 'cleanup-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $context = new TenantExecutionContext($church->id, $user->id);

        app(TenantContext::class)->runFor($context, fn () => null);

        // No Auth::user() in this test process at all — the override must
        // not still be active after runFor() returns.
        $this->assertNull(app(TenantContext::class)->currentChurchId());
        $this->assertFalse(app(TenantContext::class)->hasContext());
    }

    public function test_run_for_clears_the_restored_context_even_when_the_callback_throws(): void
    {
        $church = Church::create(['name' => 'Cleanup Church Two', 'slug' => 'cleanup-church-two']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $context = new TenantExecutionContext($church->id, $user->id);

        try {
            app(TenantContext::class)->runFor($context, function (): never {
                throw new RuntimeException('Simulated failure inside execute().');
            });
            $this->fail('Expected the RuntimeException to propagate.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertNull(app(TenantContext::class)->currentChurchId());
        $this->assertFalse(app(TenantContext::class)->hasContext());
    }

    public function test_run_for_does_not_leave_a_trusted_actor_context_active_after_a_rejected_restoration(): void
    {
        $context = new TenantExecutionContext(999999, null);

        try {
            app(TenantContext::class)->runFor($context, fn () => null);
        } catch (UntrustedTenantExecutionException) {
            // expected
        }

        $this->assertFalse(app(TenantContext::class)->hasContext());
    }

    /**
     * The HTTP-derived resolution path (Auth/session) must remain
     * completely unaffected once a runFor() call has finished — proving
     * this is a genuinely separate, opt-in mode, not a change to existing
     * behavior.
     */
    public function test_normal_http_resolution_is_unaffected_after_a_run_for_call_completes(): void
    {
        $httpChurch = Church::create(['name' => 'HTTP Church', 'slug' => 'http-church']);
        $httpUser = User::factory()->forChurch($httpChurch, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($httpUser);

        $backgroundChurch = Church::create(['name' => 'Background Church', 'slug' => 'background-church']);
        $backgroundUser = User::factory()->forChurch($backgroundChurch, [ChurchRole::COMMUNICATIONS])->create();

        app(TenantContext::class)->runFor(
            new TenantExecutionContext($backgroundChurch->id, $backgroundUser->id),
            fn () => null
        );

        $this->assertSame($httpChurch->id, app(TenantContext::class)->currentChurchId());
    }
}
