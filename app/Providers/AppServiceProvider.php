<?php

namespace App\Providers;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Policies\BudgetPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\TransactionPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useTailwind();
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Budget::class, BudgetPolicy::class);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
