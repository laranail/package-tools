<?php

declare(strict_types=1);

// Stands in for a framework file under the package's own vendor/ (events, log, router...): a closure
// defined here is a dependency's, never the package's.
return static fn (): string => 'vendor';
