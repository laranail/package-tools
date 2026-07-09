<?php

declare(strict_types=1);

// Map form: [globalKey => defaults]. Merged onto each global key, host wins.
return [
    'app' => [
        'pkg_flag' => true,
    ],
    'custom' => [
        'x' => 1,
    ],
];
