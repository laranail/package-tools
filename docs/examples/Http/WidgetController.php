<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Example: a web controller extending the package-tools WebController.
|------------------------------------------------------------------------------
| WebController is a thin base that ships the same trait set a fresh Laravel
| app controller gets (AuthorizesRequests, DispatchesJobs, ValidatesRequests),
| so package controllers have parity with application controllers.
|
| The #[AsRoute] attributes are picked up by ->discoversWithAttributes() and
| registered at boot. The attribute is repeatable, so one controller can
| declare several routes.
*/

namespace Acme\Hello\Http;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Simtabi\Laranail\PackageTools\Attributes\AsRoute;
use Simtabi\Laranail\PackageTools\Http\Controllers\WebController;

#[AsRoute(method: 'GET', uri: '/hello/widgets', name: 'hello.widgets.index')]
#[AsRoute(method: 'POST', uri: '/hello/widgets', name: 'hello.widgets.store', middleware: ['web'])]
final class WidgetController extends WebController
{
    public function index(): View
    {
        return view('hello::widgets.index', ['widgets' => ['alpha', 'beta']]);
    }

    public function store(Request $request): RedirectResponse
    {
        // ValidatesRequests comes from WebController's trait set.
        $data = $this->validate($request, ['name' => ['required', 'string', 'max:64']]);

        // ... persist $data['name'] ...

        return redirect()->route('hello.widgets.index');
    }
}
