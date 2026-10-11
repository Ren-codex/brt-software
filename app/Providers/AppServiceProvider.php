<?php

namespace App\Providers;

use App\Listeners\LoginFailed;
use App\Listeners\LoginSuccessful;
use App\Observers\ApprovalRequestObserver;
use App\Services\Approvals\ApprovalRegistry;
use App\Support\ListSorting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

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
        \Schema::defaultStringLength(191);

        Event::listen(
            LoginSuccessful::class,
            LoginFailed::class
        );

        Inertia::share([
            'auth' => [
                'user' => auth()->user(),
            ],
        ]);

        $this->registerApprovalObservers();

        // Adds ->sortFromRequest() to every Eloquent query, for sortable lists.
        ListSorting::register();

        // Every print carries "Page 1 of 2" in its bottom-right corner. The PDF
        // package registers itself lazily, so its wrapper is swapped with an
        // extender, which still runs after that happens; a plain bind here
        // would be overwritten the first time a PDF was made.
        $this->app->extend('dompdf.wrapper', fn ($pdf, $app) => new \App\Support\NumberedPdf(
            $app['dompdf'], $app['config'], $app['files'], $app['view']
        ));
    }

    /**
     * Watch every model that can wait on an approver, so the approvers get told.
     *
     * The list comes from ApprovalRegistry rather than being repeated here —
     * adding a source there is enough to start notifying for it.
     */
    private function registerApprovalObservers(): void
    {
        // Once per model: a sales order is two sources, and observing it twice
        // would announce everything about it twice.
        $models = array_unique(array_column(app(ApprovalRegistry::class)->sources(), 'model'));

        foreach ($models as $model) {
            $model::observe(ApprovalRequestObserver::class);
        }
    }
}
