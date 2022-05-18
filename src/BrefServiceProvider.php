<?php

namespace CacheWerk\BrefLaravelBridge;

use Monolog\Formatter\JsonFormatter;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class BrefServiceProvider extends ServiceProvider
{
    /**
     * Set up Bref integration.
     *
     * @return void
     */
    public function register()
    {
        if (! isset($_SERVER['LAMBDA_TASK_ROOT'])) {
            return;
        }

        $this->mergeConfigFrom(__DIR__ . '/../config/bref.php', 'bref');

        $this->app[Kernel::class]->pushMiddleware(Http\Middleware\ServeStaticAssets::class);

        $this->app->rebinding('request', function ($app, $request) {
            $app->make('log')->shareContext([
                'requestId' => $request->header('X-Request-ID'),
            ]);
        });

        $this->fixDefaultConfiguration();

        Config::set('app.mix_url', Config::get('app.asset_url'));

        Config::set('logging.channels.stderr.formatter', JsonFormatter::class);

        Config::set('trustedproxy.proxies', ['0.0.0.0/0', '2000:0:0:0:0:0:0:0/3']);

        Config::set('view.compiled', StorageDirectories::Path . '/framework/views');
        Config::set('cache.stores.file.path', StorageDirectories::Path . '/framework/cache');

        Config::set('cache.stores.dynamodb.key');
        Config::set('cache.stores.dynamodb.secret');
        Config::set('cache.stores.dynamodb.token', env('AWS_SESSION_TOKEN'));

        Config::set('filesystems.disks.s3.key');
        Config::set('filesystems.disks.s3.secret');
        Config::set('filesystems.disks.s3.token', env('AWS_SESSION_TOKEN'));

        $account = env('AWS_ACCOUNT_ID');
        $region = env('AWS_REGION', env('AWS_DEFAULT_REGION', 'us-east-1'));

        Config::set('queue.connections.sqs.key');
        Config::set('queue.connections.sqs.secret');
        Config::set('queue.connections.sqs.token', env('AWS_SESSION_TOKEN'));
        Config::set('queue.connections.sqs.prefix', env('SQS_PREFIX', "https://sqs.{$region}.amazonaws.com/{$account}"));
    }

    /**
     * Bootstrap package services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/bref.php' => config_path('bref.php'),
            ], 'bref-config');

            $this->publishes([
                __DIR__ . '/../stubs/runtime.php' => base_path('php/runtime.php'),
            ], 'bref-runtime');
        }
    }

    /**
     * Prevent the default Laravel configuration from causing errors.
     *
     * @return void
     */
    protected function fixDefaultConfiguration()
    {
        if (Config::get('session.driver') === 'file') {
            Config::set('session.driver', 'cookie');
        }

        if (Config::get('filesystems.default') === 'local') {
            Config::set('filesystems.default', 's3');
        }

        if (Config::get('logging.default') === 'stack') {
            Config::set('logging.default', 'stderr');
        }
    }
}
