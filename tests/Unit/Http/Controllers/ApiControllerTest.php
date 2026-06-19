<?php

declare(strict_types=1);

namespace Simtabi\Laranail\PackageTools\Tests\Unit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Simtabi\Laranail\PackageTools\Http\Controllers\ApiController;
use Simtabi\Laranail\PackageTools\Tests\TestCase;

final class ApiControllerTest extends TestCase
{
    public function test_respond_with_success_emits_200_and_success_true(): void
    {
        $controller = new class extends ApiController
        {
            public function call(): JsonResponse
            {
                return $this->respondWithSuccess();
            }
        };

        $response = $controller->call();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame(['success' => true], $response->getData(true));
    }

    public function test_error_helpers_emit_correct_status_and_message_shape(): void
    {
        $controller = new class extends ApiController {};

        $cases = [
            ['errorForbidden', 'Forbidden', Response::HTTP_FORBIDDEN],
            ['errorUnauthorized', 'Unauthorized', Response::HTTP_UNAUTHORIZED],
            ['errorNotFound', 'Resource Not Found', Response::HTTP_NOT_FOUND],
            ['errorInternalError', 'Internal Error', Response::HTTP_INTERNAL_SERVER_ERROR],
        ];

        foreach ($cases as [$method, $expectedMessage, $expectedStatus]) {
            $response = $controller->{$method}();

            self::assertInstanceOf(JsonResponse::class, $response, "{$method} must return JsonResponse");
            self::assertSame($expectedStatus, $response->getStatusCode(), "{$method} status");
            self::assertSame(['message' => $expectedMessage], $response->getData(true), "{$method} body");
        }
    }

    public function test_error_helpers_accept_custom_message(): void
    {
        $controller = new class extends ApiController {};

        $response = $controller->errorNotFound('Widget gone');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertSame(['message' => 'Widget gone'], $response->getData(true));
    }

    public function test_set_status_code_returns_self_for_chaining(): void
    {
        $controller = new class extends ApiController {};

        // Pick a status that isn't already the default 200, to prove the
        // setter took effect.
        $result = $controller->setStatusCode(Response::HTTP_CREATED);

        self::assertSame($controller, $result);
        self::assertSame(Response::HTTP_CREATED, $controller->getStatusCode());
    }
}
