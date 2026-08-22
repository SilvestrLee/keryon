<?php

// Keryon-owned FaithFlow provider/model seam — see K-FAITHFLOW-001B §20/§21.
// This is a default, not a lock-in: the model/provider recommendation from
// K-FAITHFLOW-001A is expected to change over time as availability, quality
// and pricing change. FaithFlow's persistence and domain model do not depend
// on the value chosen here.
return [
    'provider' => env('FAITHFLOW_PROVIDER', 'anthropic'),
    'model' => env('FAITHFLOW_MODEL', 'claude-sonnet-5'),
];
