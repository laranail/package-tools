<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Package\Tools\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Abstract base for package web controllers.
 *
 * Ships the same trait set a fresh Laravel app's controller gets, so
 * package controllers extending this have parity with application
 * controllers. Kept minimal; heavier helpers live on ApiController.
 */
abstract class WebController extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;
}
