<?php

namespace Workbench\App\Providers;

use Illuminate\Support\Env;
use Illuminate\Support\ServiceProvider;

class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Env::getRepository()->set('ADMIN_APP_URL', '');
        Env::getRepository()->set('ADMIN_APP_PATH', 'admin');

        config()->set('twill.admin_app_url', '');
        config()->set('twill.admin_app_path', 'admin');
        config()->set('translatable.locales', ['en', 'it']);
        // Act as an app that published this package's config, as the README
        // asks: unpublished, the core's defaults would win the merge. The
        // seeded translations live in lang/{locale}/test.php.
        config()->set('translation-handler', array_merge(
            config('translation-handler', []),
            require __DIR__.'/../../../config/translation-handler.php',
            ['fileNames' => ['test']],
        ));
    }

    public function boot(): void
    {
        //
    }
}
