<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof ValidationException) {
                return null;
            }

            if ($request->expectsJson()) {
                $status = 500;
                if ($e instanceof HttpExceptionInterface) {
                    $status = $e->getStatusCode();
                } elseif ($e instanceof AuthorizationException) {
                    $status = 403;
                }

                $messages = [
                    403 => 'Bạn không có quyền thực hiện thao tác này.',
                    404 => 'Không tìm thấy dữ liệu.',
                    419 => 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang.',
                    429 => 'Bạn thao tác quá nhanh. Vui lòng thử lại sau.',
                    500 => 'Có lỗi xảy ra. Vui lòng thử lại.',
                ];

                return response()->json([
                    'ok' => false,
                    'message' => $messages[$status] ?? 'Có lỗi xảy ra. Vui lòng thử lại.',
                ], $status);
            }

            return null;
        });
    })->create();
