<?php

namespace App\Providers;

use App\Services\Payments\GatewayManager;
use Illuminate\Support\ServiceProvider;

/**
 * O driver deixou de ser único: cada método tem o seu (M-Pesa fala com a
 * OpenAPI da Vodacom, e-Mola passa por um agregador), e é o GatewayManager que
 * resolve — incluindo a recusa do driver falso fora de local/testing.
 */
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GatewayManager::class, fn ($app) => new GatewayManager($app));
    }
}
