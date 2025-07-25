<?php

namespace App\Providers;

use App\Models\Agent;
use App\Models\AgentFile;
use App\Models\ModelSetting;
use App\Models\User;
use App\Observers\AgentFileObserver;
use App\Observers\AgentObserver;
use App\Observers\ModelSettingObserver;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Stripe\Stripe;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Request::macro('subdomain', function () {
            $host = $this->getHost();
            $dots = substr_count($host, '.');

            if ($dots === 2 || str_contains($host, '.localhost')) {
                return explode('.', $host)[0];
            }

            return null;
        });

        Request::macro('domain', function () {
            $host = parse_url($this->getSchemeAndHttpHost(), PHP_URL_HOST);

            preg_match('/([a-z0-9\-]+\.[a-z]{2,})$/i', $host, $matches);

            return $matches[1] ?? $host.($this->getPort() === 80 ? '' : ':'.$this->getPort());
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
//        Cashier::calculateTaxes();

//        Cashier::keepIncompleteSubscriptionsActive();

        Stripe::setApiKey(config('cashier.secret'));

        User::observe(UserObserver::class);
        Agent::observe(AgentObserver::class);
        AgentFile::observe(AgentFileObserver::class);
        ModelSetting::observe(ModelSettingObserver::class);
    }
}
