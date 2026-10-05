<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Enums;

/**
 * How a deprecated alias announces itself when it is used.
 *
 *  - {@see self::TriggerError}: `trigger_error(..., E_USER_DEPRECATED)`. Surfaces in test suites,
 *    in Laravel's deprecations log channel, and in any error handler watching deprecations.
 *  - {@see self::Log}: a `warning` on a PSR-3 logger. For hosts that do not route deprecations.
 *  - {@see self::None}: silent. For a name the package still resolves on purpose and has no plan to
 *    remove, such as a framework name the host itself reads back.
 *
 * Whichever is chosen, a name is announced once per process, not once per use: a link rendered on
 * every page would otherwise write a line per request.
 */
enum DeprecationNotice
{
    case TriggerError;
    case Log;
    case None;
}
