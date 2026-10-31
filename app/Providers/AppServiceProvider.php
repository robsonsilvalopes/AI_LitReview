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
        //Pulse
        Gate::define('viewPulse', function($user){
          return $user->email === 'anderson@deizepe.com.br';
        });
        // Gate::define('viewPulse', function($user=null){
        //   return true;
        // });
    }
}
