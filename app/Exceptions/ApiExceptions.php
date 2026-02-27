<?php

namespace App\Exceptions;

use App\Enum\Common\ActionCodeEnum;
use App\Traits\ApiResponserTrait;
use Exception;
use BadMethodCallException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

use Illuminate\Support\Facades\Route as RouteFacade;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException as SymfonyNotFoundHttpException;


class ApiExceptions extends Exception
{
    use ApiResponserTrait;

    public function handleException($request, Exception $exception)
    {
        // Default Log here
        // Log::error($exception->getMessage(), [
        //     'exception' => $exception,
        //     'trace' => $exception->getTraceAsString(),
        // ]);
        // Log::error($exception); // Simple log

        // Handle different exception types
        if ($exception instanceof ValidationException) {
            return $this->convertValidationExceptionToResponse($exception, $request);
        }

        if ($exception instanceof ModelNotFoundException) {
            $modelName = strtolower(class_basename($exception->getModel()));
            return $this->showErrorMessage("Does not exist any {$modelName} model", 404);
        }

        if ($exception instanceof AuthenticationException) {
            return $this->unauthenticated($request, $exception);
        }

        if ($exception instanceof AuthorizationException) {
            return $this->showErrorMessage($exception->getMessage(), 403);
        }

        // Sanctum: invalid or missing token (most common case)
        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException) {
            return $this->showErrorMessage('Unauthorized: missing or invalid authentication token.', 401);
        }

        // Sanctum: missing ability on token
        if ($exception instanceof \Laravel\Sanctum\Exceptions\MissingAbilityException) {
            return $this->showErrorMessage('You do not have the required ability to access this resource.', 403);
        }

        // Distinguish between "real" 404 and auth failure that throws RouteNotFoundException
        if ($exception instanceof RouteNotFoundException) {
            try {
                // Try to match the incoming request against the registered routes.
                // If the match succeeds, the route exists and the RouteNotFoundException
                // likely came from an auth guard — return 401.
                RouteFacade::getRoutes()->match($request);
                return $this->showErrorMessage('Unauthenticated: access token missing or invalid.', 401);
            } catch (SymfonyNotFoundHttpException $e) {
                // No route matched the request — real 404
                return $this->showErrorMessage('The specified route/url was not found.', 404);
            } catch (MethodNotAllowedHttpException $e) {
                // If route matched by path but method not allowed, return 405
                return $this->showErrorMessage('Method not allowed for this request.', 405);
            } catch (\Exception $e) {
                // Any unexpected error while matching — fallback to 404 to avoid exposing internals
                // return $this->errorResponse('The specified route/url was not found.', 404);
                return $this->showErrorMessage($e->getMessage(), $e->getCode() ?: 500);
            }
        }

        // Standard NotFoundHttpException (fallback)
        if ($exception instanceof NotFoundHttpException) {
            return $this->showErrorMessage('The specified route/url was not found.', 404);
        }



        // if ($exception instanceof RouteNotFoundException || $exception instanceof NotFoundHttpException) {
        //     return $this->showErrorMessage('The specified route/url was not found.', 404);
        // }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return $this->showErrorMessage('Method not allowed for this request.', 405);
        }

        if ($exception instanceof BadMethodCallException) {
            return $this->showErrorMessage('Invalid method called for the request.', 405);
        }

        if ($exception instanceof HttpException) {
            return $this->showErrorMessage($exception->getMessage(), $exception->getStatusCode());
        }

        if ($exception instanceof QueryException) {
            // Handle specific SQL error codes, e.g., foreign key constraint violations
            $errorCode = $exception->errorInfo[1];
            if ($errorCode == 1451) {
                return $this->showErrorMessage('Cannot remove this resource permanently. It is related to another resource.', 409);
            }
            return $this->showErrorMessage($exception->getMessage(), 500);
        }

        if ($exception instanceof TokenMismatchException) {
            return redirect()->back()->withInput($request->input());
        }

        if ($exception instanceof RuntimeException) {

            return $this->showErrorMessage(
                $exception->getMessage() ?: 'Transaction failed. Changes were rolled back.',
                500
            );
        }




        return $this->showErrorMessage('Unexpected error occurred. Please try again later.', 500);
    }

    // Handle unauthenticated exceptions
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($this->isFrontend($request)) {
            return redirect()->guest('login');
        }

        // return $this->errorResponse('Unauthenticated.', 401, ActionCodeEnum::FORCE_LOGIN);
        return $this->showErrorMessageWithAction('Unauthenticated.', 401, ActionCodeEnum::FORCE_LOGIN);
    }

    // Convert validation exceptions to a response
    protected function convertValidationExceptionToResponse(ValidationException $e, $request)
    {
        $errors = $e->errors();

        if ($this->isFrontend($request)) {
            return redirect()->back()->withErrors($errors)->withInput($request->input());
        }

        return $this->showErrorMessage($errors, 422);
    }

    // Check if the request is from the frontend (HTML)
    private function isFrontend($request)
    {
        $route = $request->route();
        return $route && $request->acceptsHtml() && collect($route->middleware())->contains('web');
    }
}
