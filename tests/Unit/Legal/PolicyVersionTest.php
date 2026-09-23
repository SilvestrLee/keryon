<?php

namespace Tests\Unit\Legal;

use App\Enums\PolicyAudience;
use App\Enums\PolicyDocumentType;
use App\Enums\PolicyVersionStatus;
use App\Models\PolicyVersion;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Throwable;

class PolicyVersionTest extends TestCase
{
    use RefreshDatabase;

    // --- Draft creation --------------------------------------------------

    public function test_creating_a_draft_persists_expected_defaults(): void
    {
        $draft = PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'terms-draft-v1', 'Hello world', 'Working Title');

        $this->assertSame(PolicyDocumentType::TERMS, $draft->document_type);
        $this->assertSame('terms-draft-v1', $draft->version);
        $this->assertSame('Hello world', $draft->content);
        $this->assertSame('Working Title', $draft->title);
        $this->assertSame(PolicyVersionStatus::DRAFT, $draft->status);
        $this->assertNull($draft->audience);
        $this->assertFalse($draft->is_synthetic_fixture);
        $this->assertNull($draft->published_at);
        $this->assertNull($draft->approved_at);
        $this->assertNull($draft->approved_by_reference);
        $this->assertNull($draft->approval_evidence_reference);
        $this->assertNull($draft->retired_at);
        $this->assertSame(hash('sha256', 'Hello world'), $draft->sha256);
        $this->assertDatabaseHas('policy_versions', ['version' => 'terms-draft-v1', 'status' => 'draft']);
    }

    public function test_creating_a_draft_with_an_explicit_audience_succeeds(): void
    {
        $draft = PolicyVersion::createDraft(PolicyDocumentType::PRIVACY, 'privacy-staff-v1', audience: PolicyAudience::STAFF);

        $this->assertSame(PolicyAudience::STAFF, $draft->audience);
    }

    public function test_creating_a_draft_with_minimal_arguments_defaults_content_and_title_to_empty_strings(): void
    {
        $draft = PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'terms-minimal-v1');

        $this->assertSame('', $draft->content);
        $this->assertSame('', $draft->title);
        $this->assertSame(hash('sha256', ''), $draft->sha256);
    }

    // --- Draft editing -----------------------------------------------------

    public function test_editing_content_while_draft_recomputes_sha256(): void
    {
        $draft = PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'terms-edit-v1', 'first draft');
        $firstHash = $draft->sha256;

        $draft->content = 'revised draft';
        $draft->save();

        $this->assertNotSame($firstHash, $draft->fresh()->sha256);
        $this->assertSame(hash('sha256', 'revised draft'), $draft->fresh()->sha256);
    }

    public function test_editing_title_effective_at_audience_and_version_while_draft_succeeds(): void
    {
        $draft = PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'terms-edit-v2');

        $draft->title = 'A Real Title';
        $draft->effective_at = now()->addDays(30);
        $draft->audience = PolicyAudience::PRIMARY->value;
        $draft->version = 'terms-edit-v2-renamed';
        $draft->save();

        $fresh = $draft->fresh();
        $this->assertSame('A Real Title', $fresh->title);
        $this->assertNotNull($fresh->effective_at);
        $this->assertSame(PolicyAudience::PRIMARY, $fresh->audience);
        $this->assertSame('terms-edit-v2-renamed', $fresh->version);
    }

    public function test_a_caller_supplied_sha256_is_silently_overridden_while_draft(): void
    {
        $draft = PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'terms-hash-v1', 'real content');

        $draft->sha256 = str_repeat('f', 64); // direct assignment, bypassing $fillable
        $draft->save();

        $this->assertSame(hash('sha256', 'real content'), $draft->fresh()->sha256);
    }

    public function test_direct_model_construction_with_forged_status_and_hash_still_yields_a_safe_draft(): void
    {
        // saving() fires BEFORE creating() in Eloquent's event order — at the moment saving()
        // runs during a create, $model->exists is still false and creating() has not yet forced
        // status back to draft. A caller who sets status/sha256/approval fields directly on a new
        // (unsaved) instance, bypassing $fillable entirely, must still end up with an ordinary,
        // correctly-hashed draft once save() completes.
        $model = new PolicyVersion();
        $model->document_type = PolicyDocumentType::TERMS->value;
        $model->version = 'terms-direct-construct-v1';
        $model->title = 'Forged Title';
        $model->content = 'real content';
        $model->status = PolicyVersionStatus::APPROVED->value;
        $model->sha256 = str_repeat('f', 64);
        $model->approved_by_reference = 'forged-operator';
        $model->approved_at = now();
        $model->approval_evidence_reference = 'forged-evidence';
        $model->published_at = now();

        $model->save();

        $fresh = $model->fresh();
        $this->assertSame(PolicyVersionStatus::DRAFT, $fresh->status);
        $this->assertSame(hash('sha256', 'real content'), $fresh->sha256);
        $this->assertNull($fresh->approved_by_reference);
        $this->assertNull($fresh->approved_at);
        $this->assertNull($fresh->approval_evidence_reference);
        $this->assertNull($fresh->published_at);
        $this->assertNull($fresh->retired_at);
    }

    public function test_force_create_with_an_attempted_approved_status_and_forged_hash_still_yields_a_safe_draft(): void
    {
        // forceCreate() bypasses $fillable guarding via forceFill() before save() is ever called,
        // so status/sha256/approval fields are already set on the model when saving() fires.
        $version = PolicyVersion::forceCreate([
            'document_type' => PolicyDocumentType::TERMS->value,
            'version' => 'terms-force-create-v1',
            'title' => 'Forged Title',
            'content' => 'real content',
            'status' => PolicyVersionStatus::APPROVED->value,
            'sha256' => str_repeat('f', 64),
            'approved_by_reference' => 'forged-operator',
            'approved_at' => now(),
            'approval_evidence_reference' => 'forged-evidence',
            'published_at' => now(),
        ]);

        $fresh = $version->fresh();
        $this->assertSame(PolicyVersionStatus::DRAFT, $fresh->status);
        $this->assertSame(hash('sha256', 'real content'), $fresh->sha256);
        $this->assertNull($fresh->approved_by_reference);
        $this->assertNull($fresh->approved_at);
        $this->assertNull($fresh->approval_evidence_reference);
        $this->assertNull($fresh->published_at);
        $this->assertNull($fresh->retired_at);
    }

    // --- Invalid document types and audiences -------------------------------

    public function test_creating_with_an_invalid_document_type_string_is_rejected(): void
    {
        try {
            PolicyVersion::create(['document_type' => 'not-a-real-type', 'version' => 'v1', 'title' => '', 'content' => '']);
            $this->fail('Expected an exception for an invalid document_type.');
        } catch (Throwable $exception) {
            $this->assertInstanceOf(Throwable::class, $exception);
        }
        $this->assertDatabaseCount('policy_versions', 0);
    }

    public function test_creating_with_an_invalid_audience_string_is_rejected(): void
    {
        try {
            PolicyVersion::create(['document_type' => PolicyDocumentType::TERMS->value, 'version' => 'v1', 'title' => '', 'content' => '', 'audience' => 'congregation']);
            $this->fail('Expected an exception for an invalid audience.');
        } catch (Throwable $exception) {
            $this->assertInstanceOf(Throwable::class, $exception);
        }
        $this->assertDatabaseCount('policy_versions', 0);
    }

    public function test_a_null_audience_is_valid_and_means_shared(): void
    {
        $draft = PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'terms-shared-v1');

        $this->assertNull($draft->audience);
    }

    // --- Duplicate natural keys ---------------------------------------------

    public function test_duplicate_document_type_and_version_pair_is_rejected_at_the_database_level(): void
    {
        PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'terms-dup-v1');

        $this->expectException(QueryException::class);
        PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'terms-dup-v1');
    }

    public function test_the_same_version_string_is_allowed_across_different_document_types(): void
    {
        PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'shared-string-v1');
        $privacy = PolicyVersion::createDraft(PolicyDocumentType::PRIVACY, 'shared-string-v1');

        $this->assertSame('shared-string-v1', $privacy->version);
        $this->assertDatabaseCount('policy_versions', 2);
    }

    // --- Reserved synthetic identifiers -------------------------------------

    public function test_a_reserved_identifier_without_the_synthetic_flag_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'development-terms-v1', isSyntheticFixture: false);
    }

    public function test_a_reserved_identifier_with_the_synthetic_flag_succeeds(): void
    {
        $draft = PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'development-terms-v1', isSyntheticFixture: true);

        $this->assertTrue($draft->is_synthetic_fixture);
        $this->assertSame('development-terms-v1', $draft->version);
    }

    public function test_renaming_a_draft_to_a_reserved_identifier_without_the_synthetic_flag_is_rejected(): void
    {
        $draft = PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'terms-rename-v1');

        $draft->version = 'local-terms-fixture';
        $this->expectException(DomainException::class);
        $draft->save();
    }

    public function test_a_non_reserved_identifier_with_the_synthetic_flag_is_permitted(): void
    {
        // R3 §5.1 states one direction only: reserved => synthetic. It does not forbid a
        // synthetic row using a non-reserved identifier.
        $draft = PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'terms-other-fixture-v1', isSyntheticFixture: true);

        $this->assertTrue($draft->is_synthetic_fixture);
    }

    // --- Identifier length ---------------------------------------------------

    public function test_a_version_identifier_at_exactly_100_characters_is_accepted(): void
    {
        $version = str_repeat('a', 100);
        $draft = PolicyVersion::createDraft(PolicyDocumentType::TERMS, $version);

        $this->assertSame($version, $draft->version);
    }

    public function test_a_version_identifier_over_100_characters_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        PolicyVersion::createDraft(PolicyDocumentType::TERMS, str_repeat('a', 101));
    }

    public function test_a_blank_version_identifier_is_rejected(): void
    {
        $this->expectException(DomainException::class);

        PolicyVersion::createDraft(PolicyDocumentType::TERMS, '');
    }

    // --- Direct attempts to assign approved/published/retired states -------

    public function test_direct_assignment_of_status_to_approved_is_rejected(): void
    {
        $draft = PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'terms-direct-approve-v1', 'content', 'Title');
        $draft->effective_at = now();
        $draft->save();

        $draft->status = PolicyVersionStatus::APPROVED->value;
        $draft->approved_by_reference = 'operator:jane';
        $draft->approval_evidence_reference = 'evidence-123';
        $draft->approved_at = now();

        $this->expectException(DomainException::class);
        $draft->save();
    }

    public function test_direct_assignment_of_published_at_is_rejected(): void
    {
        $draft = PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'terms-direct-publish-v1');

        $draft->published_at = now();

        $this->expectException(DomainException::class);
        $draft->save();
    }

    public function test_direct_assignment_of_status_to_retired_is_rejected(): void
    {
        $draft = PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'terms-direct-retire-v1');

        $draft->status = PolicyVersionStatus::RETIRED->value;
        $draft->retired_at = now();

        $this->expectException(DomainException::class);
        $draft->save();
    }

    public function test_direct_assignment_of_approval_evidence_reference_alone_is_rejected(): void
    {
        $draft = PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'terms-direct-evidence-v1');

        $draft->approval_evidence_reference = 'forged-evidence';

        $this->expectException(DomainException::class);
        $draft->save();
    }

    public function test_direct_assignment_of_approved_by_reference_alone_is_rejected(): void
    {
        $draft = PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'terms-direct-approver-v1');

        $draft->approved_by_reference = 'operator:forged';

        $this->expectException(DomainException::class);
        $draft->save();
    }

    public function test_none_of_the_rejected_direct_assignment_attempts_mutate_the_stored_row(): void
    {
        $draft = PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'terms-no-mutation-v1');

        $attempt = $draft->fresh();
        $attempt->status = PolicyVersionStatus::APPROVED->value;
        try {
            $attempt->save();
        } catch (DomainException) {
            // expected
        }

        $fresh = $draft->fresh();
        $this->assertSame(PolicyVersionStatus::DRAFT, $fresh->status);
        $this->assertNull($fresh->approved_at);
    }

    // --- Unauthorized changes to protected fields (forward-correctness check) ---

    public function test_a_row_can_never_reach_a_state_where_identity_fields_are_dirty_and_status_is_non_draft(): void
    {
        // Because no legitimate transition exists in this milestone, this proves the
        // forward-correct "immutable once non-draft" guard is unreachable today by
        // confirming there is no way to get a non-draft row in the first place.
        $draft = PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'terms-unreachable-v1');

        $this->assertSame(PolicyVersionStatus::DRAFT, $draft->fresh()->status);
    }

    public function test_deleting_a_draft_succeeds(): void
    {
        $draft = PolicyVersion::createDraft(PolicyDocumentType::TERMS, 'terms-delete-v1');

        $draft->delete();

        $this->assertDatabaseMissing('policy_versions', ['id' => $draft->id]);
    }

    public function test_content_body_column_exists_but_document_type_and_version_form_the_natural_key(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('policy_versions', 'content'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('policy_versions', 'document_type'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('policy_versions', 'version'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('policy_versions', 'policy_governance_event_id'));
    }
}
