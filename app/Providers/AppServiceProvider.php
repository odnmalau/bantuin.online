<?php

namespace App\Providers;

use App\Ai\Providers\QwenProvider;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Ai\Ai;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAi();
        $this->configureDefaults();
    }

    /**
     * Configure application AI providers.
     */
    protected function configureAi(): void
    {
        Ai::extend('qwen', fn ($app, array $config): QwenProvider => new QwenProvider(
            $config,
            $app->make(Dispatcher::class),
            $app->make(HttpFactory::class),
        ));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        $isProduction = app()->isProduction();

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            $isProduction,
        );

        Password::defaults(function () use ($isProduction): ?Password {
            if (! $isProduction) {
                return null;
            }

            return Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised();
        });
    }
}
