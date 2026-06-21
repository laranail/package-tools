<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Example: a view composer registered by attribute discovery.
|------------------------------------------------------------------------------
| With ->discoversWithAttributes() set, #[AsViewComposer] is wired through
| hasViewComposer() once per listed view. Laravel then calls compose() before
| each of those views renders, so they all receive the shared data.
|
| `views` accepts a single string or a list of view names.
*/

namespace Acme\Hello\Http;

use Illuminate\Contracts\View\View;
use Simtabi\Laranail\Package\Tools\Attributes\AsViewComposer;

#[AsViewComposer(views: ['hello::widgets.index', 'hello::layouts.app'])]
final class GreetingComposer
{
    public function compose(View $view): void
    {
        $view->with('greeting', config('hello.greeting', 'Hello'));
    }
}
