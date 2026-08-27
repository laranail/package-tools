<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Support\Dist;

/**
 * What became of a path the manifest promises a consumer will find.
 */
enum ReferenceStatus
{
    /** Committed, and present in the dist archive. */
    case Shipped;

    /**
     * Committed, but `.gitattributes` strips it from the archive. This is the
     * failure the audit exists for: the manifest points at a file a dist
     * install will not contain.
     */
    case Stripped;

    /**
     * Declared in the manifest but never committed. Composer tolerates a
     * missing psr-4 directory, so this is reported rather than failed -- but
     * it is still a manifest describing something that does not exist.
     */
    case NotCommitted;
}
