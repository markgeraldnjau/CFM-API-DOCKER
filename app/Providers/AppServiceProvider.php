<?php

namespace App\Providers;

use App\Rules\NegTag;
use App\Rules\PhoneNumber;
use App\Rules\StripTag;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

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
        //
        Validator::extend(StripTag::handle(), StripTag::class);
        Validator::extend(NegTag::handle(), NegTag::class);
        Validator::extend(PhoneNumber::handle(), PhoneNumber::class);
    }
}
