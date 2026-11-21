<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //HTTPS
        if(config('app.env') != 'local'){
          URL::forceScheme('https');
        }
        // Pulse dashboard (/pulse): only the e-mails listed in PULSE_ADMIN_EMAILS (comma-separated);
        // nobody when it is empty
        Gate::define('viewPulse', function ($user) {
          $admins = array_filter(array_map(
            fn ($email) => strtolower(trim($email)),
            explode(',', (string) config('services.pulse.admin_emails'))
          ));

          return $user && in_array(strtolower((string) $user->email), $admins, true);
        });
    }
}
