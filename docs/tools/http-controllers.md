# HTTP controllers

Optional abstract base controllers for package HTTP and JSON endpoints.

Two optional abstract base controllers for package controllers, under
`Simtabi\Laranail\PackageTools\Http\Controllers\`. They are plain base
classes — extend them in a package's own controllers; nothing
auto-registers them.

## `WebController`

`abstract class WebController extends Illuminate\Routing\Controller`.

It uses the same trait set Laravel emits for `make:controller`, so a
package controller has parity with an application controller from the
first line:

- `Illuminate\Foundation\Auth\Access\AuthorizesRequests`
- `Illuminate\Foundation\Bus\DispatchesJobs`
- `Illuminate\Foundation\Validation\ValidatesRequests`

```php
use Simtabi\Laranail\PackageTools\Http\Controllers\WebController;

class WidgetController extends WebController
{
    // authorize(), dispatch(), validate() already in scope.
}
```

## `ApiController`

`abstract class ApiController extends WebController` — inherits the three
traits above and adds typed `Illuminate\Http\JsonResponse` helpers.

It carries a mutable status code (`Response::HTTP_OK` by default):

| Method | Returns | Notes |
|---|---|---|
| `getStatusCode()` | `int` | Current status code. |
| `setStatusCode(int $statusCode)` | `static` | Set it; chainable. |

### Response helpers

| Method | Status | Response body |
|---|---|---|
| `respondWithArray(array $payload, array $headers = [])` | current status code | `$payload` verbatim |
| `respondWithSuccess(int $statusCode = 200)` | given (default `200`) | `{ "success": true }` |
| `respondWithError(string $message)` | current status code | `{ "message": "<message>" }` |
| `errorForbidden(string $message = 'Forbidden')` | `403` | `{ "message": "Forbidden" }` |
| `errorUnauthorized(string $message = 'Unauthorized')` | `401` | `{ "message": "Unauthorized" }` |
| `errorNotFound(string $message = 'Resource Not Found')` | `404` | `{ "message": "Resource Not Found" }` |
| `errorInternalError(string $message = 'Internal Error')` | `500` | `{ "message": "Internal Error" }` |

`respondWithArray`, `respondWithSuccess`, and `respondWithError` are
`protected`; the four `errorXxx` helpers are `public`. The `errorXxx`
helpers set the status code before delegating to `respondWithError`. If
you call `respondWithError()` directly, set the status code first (via
`setStatusCode()`) so you don't send a `200`-with-error response.

```php
use Simtabi\Laranail\PackageTools\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class WidgetApiController extends ApiController
{
    public function show(int $id): JsonResponse
    {
        $widget = Widget::find($id);

        if ($widget === null) {
            return $this->errorNotFound();           // 404 { "message": "Resource Not Found" }
        }

        return $this->setStatusCode(200)
            ->respondWithArray(['data' => $widget]); // 200 { "data": { … } }
    }
}
```

## See also

- [attribute-discovery.md](attribute-discovery.md) — `#[AsRoute]` to bind
  these controllers to routes without a route file.
- [configuration.md](../configuration.md) — `hasRoute()` / `hasRoutes()`
  for registering package route files explicitly.
- [examples/Http/WidgetController.php](../examples/Http/WidgetController.php)
  — a `WebController` subclass with `#[AsRoute]` attributes.
- [examples/Http/WidgetApiController.php](../examples/Http/WidgetApiController.php)
  — an `ApiController` subclass exercising each JSON helper.

[← Docs index](../../README.md#documentation)
