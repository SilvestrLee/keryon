<?php

return [
    'local_invitation_surface' => env('CHURCH_STAFF_LOCAL_INVITATION_SURFACE', false),
    'terms_version' => env('CHURCH_STAFF_TERMS_VERSION', in_array(env('APP_ENV'), ['local', 'testing'], true) ? 'local-terms-fixture' : null),
    'privacy_version' => env('CHURCH_STAFF_PRIVACY_VERSION', in_array(env('APP_ENV'), ['local', 'testing'], true) ? 'local-privacy-fixture' : null),
];
