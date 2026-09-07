<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [\App\Http\Middleware\AuthentikSsoAuth::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // A request body over PHP's post_max_size never reaches Laravel's
        // 'max' validation rule - it's rejected by the framework before the
        // controller runs, so without this it surfaces as a raw error page.
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'The uploaded file is too large.'], 413);
            }
            return redirect()->back()->with('error', 'The uploaded file is too large for the server to accept.');
        });

        // Sessions, cache and queue all use the `database` driver here, so a
        // MySQL container being down surfaces as a QueryException on almost
        // every request - including ones with nothing to do with the page
        // the user asked for. Only intercept actual connection failures
        // (host unreachable / refused) so a genuine bad-query bug still
        // shows up normally instead of being masked as "DB is down".
        $exceptions->render(function (\Illuminate\Database\QueryException $e, $request) {
            $message = $e->getMessage();
            $isConnectionFailure = str_contains($message, 'SQLSTATE[HY000] [2002]')
                || str_contains($message, 'Connection refused')
                || str_contains($message, 'No such host is known')
                || str_contains($message, "Unknown MySQL server host")
                || str_contains($message, 'Connection timed out');

            if (!$isConnectionFailure) {
                return null; // fall through to normal exception handling
            }

            \Illuminate\Support\Facades\Log::error('Database appears to be unreachable', ['error' => $message]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'The database is temporarily unavailable. Please try again shortly.'], 503);
            }
            return response()->view('errors.503', [
                'message' => 'The database is temporarily unavailable. Please try again shortly.',
            ], 503);
        });
    })->create();
