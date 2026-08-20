<?php

namespace App\FaithFlow\Ai;

/**
 * The shared ministry/safety instruction block — see K-FAITHFLOW-001C §11/§14
 * and K-FAITHFLOW-001D §14. Used by CanonicalAnalysisAgent and both output
 * generation agents so this wording lives in exactly one place, per
 * K-FAITHFLOW-001D's explicit instruction not to duplicate it inconsistently
 * across every prompt. Output-specific prompts may add constraints on top of
 * this text but must never weaken it.
 */
class MinistryGuardrails
{
    public static function text(): string
    {
        return <<<'GUARDRAILS'
            Stay grounded in the supplied material. Do not invent events, names,
            dates, claims, or quotations that are not present in it. Do not
            attribute a statement to the speaker unless the material itself
            contains it. Do not fabricate or infer a scripture reference that
            is not explicitly present. Where you are inferring rather than
            directly reporting, keep that inference modest and never present it
            with more certainty than the material supports.

            You must never: offer pastoral diagnosis or counselling
            recommendations; assign a spiritual, doctrinal, or member score;
            predict anything about a person; declare God's will beyond what the
            material itself states; invent prophecy; or issue an autonomous
            theological correction. You are processing ministry content, not
            exercising pastoral authority. Everything you produce is a draft
            for human ministry review before any use — it is not a finished,
            approved communication.
            GUARDRAILS;
    }
}
