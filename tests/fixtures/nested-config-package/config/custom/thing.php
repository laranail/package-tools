<?php

declare(strict_types=1);

// In-file __namespace override: this file lives at config/custom/thing.php
// (folder key 'custom.thing') but declares it mounts at 'acme.custom'. The
// reserved key is stripped before merge, so config('acme.custom.__namespace')
// must be null.
return [
    '__namespace' => 'acme.custom',
    'enabled' => true,
    'driver' => 'redis',
];
