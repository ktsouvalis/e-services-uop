<?php

namespace App\Providers;


use App\Models\Mailer;
use App\Models\Sheetmailer;
use App\Policies\MailersPolicy;
use App\Policies\SheetmailersPolicy;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(Mailer::class, MailersPolicy::class);
        Gate::policy(Sheetmailer::class, SheetmailersPolicy::class);
    }
}
