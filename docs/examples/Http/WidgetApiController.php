<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Example: a JSON API controller extending the package-tools ApiController.
|------------------------------------------------------------------------------
| ApiController adds typed JsonResponse helpers on top of WebController. Every
| helper either sets the status code itself (the errorXxx family) or emits at
| the currently-configured one (setStatusCode + respondWithArray). Each public
| method below shows one helper in a realistic spot.
*/

namespace Acme\Hello\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Simtabi\Laranail\Package\Tools\Http\Controllers\ApiController;

final class WidgetApiController extends ApiController
{
    /** @var array<string, array{id: int, name: string}> */
    private array $widgets = [
        'alpha' => ['id' => 1, 'name' => 'alpha'],
        'beta' => ['id' => 2, 'name' => 'beta'],
    ];

    // respondWithArray: arbitrary payload at the current status (200 by default).
    public function index(): JsonResponse
    {
        return $this->respondWithArray(['data' => array_values($this->widgets)]);
    }

    // setStatusCode + respondWithArray: created resource with a 201.
    public function store(Request $request): JsonResponse
    {
        $name = (string) $request->input('name', '');

        if ($name === '') {
            // errorXxx helpers set the status themselves; no setStatusCode needed.
            return $this->errorInternalError('Could not create the widget.');
        }

        return $this->setStatusCode(Response::HTTP_CREATED)
            ->respondWithArray(['data' => ['id' => 99, 'name' => $name]]);
    }

    // errorNotFound: 404 with a message body.
    public function show(string $key): JsonResponse
    {
        if (! isset($this->widgets[$key])) {
            return $this->errorNotFound("Widget '{$key}' does not exist.");
        }

        return $this->respondWithArray(['data' => $this->widgets[$key]]);
    }

    // errorUnauthorized / errorForbidden: 401 vs 403 gates.
    public function update(Request $request, string $key): JsonResponse
    {
        if (! $request->user()) {
            return $this->errorUnauthorized();
        }

        if (! $request->user()->can('update', 'widgets')) {
            return $this->errorForbidden('You may not edit widgets.');
        }

        return $this->respondWithArray(['data' => ['id' => 1, 'name' => 'updated']]);
    }

    // respondWithError: error message at the status you set first.
    public function destroy(string $key): JsonResponse
    {
        if (! isset($this->widgets[$key])) {
            return $this->setStatusCode(Response::HTTP_CONFLICT)
                ->respondWithError("Widget '{$key}' is already gone.");
        }

        // respondWithSuccess: a bare {"success": true} at the given status.
        return $this->respondWithSuccess(Response::HTTP_OK);
    }
}
