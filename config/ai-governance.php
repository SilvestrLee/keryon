<?php

return [
    /* Credentials/configuration do not constitute processor approval. */
    'providers' => [
        'anthropic' => [
            'provider' => 'anthropic',
            'service' => 'anthropic_api',
            'status' => env('AI_ANTHROPIC_APPROVAL_STATUS', 'under_review'),
            'legal_entity' => env('AI_ANTHROPIC_LEGAL_ENTITY'),
            'purpose' => 'Generate ministry communications content from Church-supplied FaithFlow source material.',
            'approved_data_classes' => ['internal', 'sensitive'],
            'media_allowed' => false,
            'region' => env('AI_ANTHROPIC_REGION', 'unknown'),
            'storage_region' => env('AI_ANTHROPIC_STORAGE_REGION', 'unknown'),
            'retention' => env('AI_ANTHROPIC_RETENTION', 'unknown'),
            'safety_retention' => env('AI_ANTHROPIC_SAFETY_RETENTION', 'unknown'),
            'training_policy' => env('AI_ANTHROPIC_TRAINING_POLICY', 'unknown'),
            'deletion_mechanism' => env('AI_ANTHROPIC_DELETION_MECHANISM', 'unknown'),
            'subprocessor_reference' => 'https://trust.anthropic.com/subprocessors',
            'contract_reference' => 'https://www.anthropic.com/legal/commercial-terms',
            'contract_status' => env('AI_ANTHROPIC_CONTRACT_STATUS', 'unverified'),
            'dpa_reference' => 'https://www.anthropic.com/legal/data-processing-addendum',
            'dpa_status' => env('AI_ANTHROPIC_DPA_STATUS', 'unverified'),
            'disclosure_required' => true,
            'reviewed_at' => env('AI_ANTHROPIC_REVIEWED_AT'),
            'evidence_reference' => env('AI_ANTHROPIC_EVIDENCE_REFERENCE', 'docs/06-Engineering/Anthropic_FaithFlow_Processor_Review.md'),
            'account_configuration_verified_at' => env('AI_ANTHROPIC_ACCOUNT_CONFIGURATION_VERIFIED_AT'),
            'configuration_constraints' => [
                'direct_anthropic_messages_api',
                'text_input_and_output_only',
                'no_media',
                'no_files_api',
                'no_tools_or_external_connectors',
                'no_provider_fallback',
                'keryon_selected_model_only',
                'human_review_required',
            ],
            'capabilities' => [
                'faithflow.analysis' => [
                    'models' => [env('FAITHFLOW_MODEL', 'claude-sonnet-5')],
                    'data_classifications' => ['internal', 'sensitive'],
                    'media_allowed' => false,
                ],
                'faithflow.generation' => [
                    'models' => [env('FAITHFLOW_MODEL', 'claude-sonnet-5')],
                    'data_classifications' => ['internal', 'sensitive'],
                    'media_allowed' => false,
                ],
            ],
        ],
    ],
];
