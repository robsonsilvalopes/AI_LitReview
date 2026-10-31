<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
  )
  ->withMiddleware(function (Middleware $middleware) {
    // Redirect unauthenticated users to login
    $middleware->redirectGuestsTo(fn () => route('login'));
    $middleware->trustProxies(at: '*');
  })
  ->withSchedule(function (Schedule $schedule) {
    // Laravel Pulse: Process metrics every minute
    $schedule->command('pulse:check')->everyMinute();
  })
  ->withExceptions(function (Exceptions $exceptions) {
    // Custom 404 error handling
    $exceptions->render(function (NotFoundHttpException $e, Request $request) {
      if ($request->expectsJson()) {
        return response()->json(['message' => 'Resource not found'], 404);
      }
      return response()->view('errors.404', [], 404);
    });
  })->create();
