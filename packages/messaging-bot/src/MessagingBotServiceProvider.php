<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LBHurtado\MessagingBot\Console\Commands\PollCommand;
use LBHurtado\MessagingBot\Console\Commands\WebhookCommand;
use LBHurtado\MessagingBot\Contracts\MessagingDriverInterface;
use LBHurtado\MessagingBot\Drivers\Telegram\TelegramDriver;
use LBHurtado\MessagingBot\Engine\IntentRouter;
use LBHurtado\MessagingBot\Engine\MessagingKernel;
use LBHurtado\MessagingBot\Services\ConversationStore;

class MessagingBotServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/messaging-bot.php',
            'messaging-bot'
        );

        $this->registerServices();
        $this->registerDrivers();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
        $this->registerPublishing();
    }

    /**
     * Register the package services.
     */
    protected function registerServices(): void
    {
        $this->app->singleton(ConversationStore::class, function ($app) {
            return new ConversationStore(
                $app['cache.store'],
                config('messaging-bot.conversation_ttl', 1800)
            );
        });

        $this->app->singleton(IntentRouter::class, function ($app) {
            return new IntentRouter(
                config('messaging-bot.handlers', []),
                config('messaging-bot.flows', [])
            );
        });

        $this->app->singleton(MessagingKernel::class, function ($app) {
            return new MessagingKernel(
                $app->make(IntentRouter::class),
                $app->make(ConversationStore::class)
            );
        });
    }

    /**
     * Register the messaging drivers.
     */
    protected function registerDrivers(): void
    {
        // Register Telegram driver
        $this->app->singleton(TelegramDriver::class, function ($app) {
            return new TelegramDriver(
                config('messaging-bot.drivers.telegram.token'),
                config('messaging-bot.drivers.telegram.webhook_secret')
            );
        });

        // Bind default driver
        $this->app->bind(MessagingDriverInterface::class, function ($app) {
            $driver = config('messaging-bot.default', 'telegram');

            return match ($driver) {
                'telegram' => $app->make(TelegramDriver::class),
                default => throw new \InvalidArgumentException("Unsupported messaging driver: {$driver}"),
            };
        });
    }

    /**
     * Register the package routes.
     */
    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::group([
            'prefix' => config('messaging-bot.routes.prefix', 'messaging'),
            'middleware' => config('messaging-bot.routes.middleware', ['api']),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/messaging.php');
        });
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/messaging-bot.php' => config_path('messaging-bot.php'),
        ], 'messaging-bot-config');

        $this->commands([
            PollCommand::class,
            WebhookCommand::class,
        ]);
    }
}
