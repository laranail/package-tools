<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Testing;

/**
 * The flat, host-owned registries a package writes names into. In each, a second package claiming
 * the same key does not collide loudly: it silently replaces the first.
 *
 * {@see RegisteredNames} reads each one live from a booted application.
 */
enum NameRegistry: string
{
    case Route = 'route';
    case RateLimiter = 'rate-limiter';
    case Command = 'command';
    case Middleware = 'middleware';
    case View = 'view';
    case Translation = 'translation';
    case Livewire = 'livewire';
    case Gate = 'gate';
    case BladeComponent = 'blade-component';
    case ContainerAlias = 'container-alias';
}
