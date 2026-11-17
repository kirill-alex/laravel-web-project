<?php

namespace App\Providers;

use App\Mail\LocalMailTransport;
use App\Notifications\NewArticleNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

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
        Mail::extend('preview', fn (): LocalMailTransport => new LocalMailTransport);

        View::composer('partials.notifications', function (ViewInstance $view): void {
            $notices = auth()->user()?->unreadNotifications()
                ->where('type', NewArticleNotification::class)->get() ?? collect();

            $view->with('unreadArticleNotifications', $notices);
        });
    }
}
