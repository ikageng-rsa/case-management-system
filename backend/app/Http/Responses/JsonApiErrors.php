<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/*
 * How the API reports what went wrong: a JSON:API document of error objects, each
 * with its status and title, a detail where there is one to give, and for a field
 * that failed validation a pointer to it in the request. What went wrong inside
 * the server stays inside unless the app is in debug mode.
 */
class JsonApiErrors
{
    public function for(Request $request, Throwable $e): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => $this->respond($e->status, $this->validationErrors($e)),
            $e instanceof AuthenticationException => $this->respond(401, [$this->error(401)]),
            $e instanceof HttpExceptionInterface => $this->respond(
                $e->getStatusCode(),
                [$this->error($e->getStatusCode(), $this->detail($e))],
                $e->getHeaders(),
            ),
            default => $this->respond(500, [$this->error(500, config('app.debug') ? $e->getMessage() : null)]),
        };
    }

    /** A record that is not there is just not found: its message would name the model behind it. */
    private function detail(HttpExceptionInterface $e): ?string
    {
        return $e->getPrevious() instanceof ModelNotFoundException ? null : $e->getMessage();
    }

    /** @return array<int, array<string, mixed>> */
    private function validationErrors(ValidationException $e): array
    {
        $errors = [];

        foreach ($e->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = $this->error($e->status, $message) + [
                    // Request bodies are flat, so "items.0.name" points at "/items/0/name".
                    'source' => ['pointer' => '/'.str_replace('.', '/', $field)],
                ];
            }
        }

        return $errors;
    }

    /** @return array<string, string> */
    private function error(int $status, ?string $detail = null): array
    {
        return array_filter([
            'status' => (string) $status,
            'title' => Response::$statusTexts[$status] ?? 'Error',
            'detail' => $detail,
        ], fn (?string $value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     * @param  array<string, string>  $headers
     */
    private function respond(int $status, array $errors, array $headers = []): JsonResponse
    {
        return new JsonResponse(['errors' => $errors], $status, ['Content-Type' => 'application/vnd.api+json'] + $headers);
    }
}
