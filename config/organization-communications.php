<?php

return [
    /*
     * K-ORG-COMMS-001C §25 — bounded, configuration-driven chunk size for
     * distribution materialization. Not a magic number scattered through
     * application code.
     */
    'distribution_chunk_size' => (int) env('ORG_COMMS_DISTRIBUTION_CHUNK_SIZE', 500),

    /*
     * K-ORG-COMMS-001C §68/§101-G — the explicit-Church targeting mode is
     * bounded to keep the persisted `target_church_ids` JSON array small,
     * validated, and historically explainable. Distributing to a larger
     * audience should use governing-scope or Unit-subtree targeting
     * instead. See the report's Product Office decision G.
     */
    'max_explicit_target_churches' => (int) env('ORG_COMMS_MAX_EXPLICIT_TARGET_CHURCHES', 200),
];
