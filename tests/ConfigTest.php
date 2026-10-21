<?php

use BrunosCode\TranslationHandler\Data\TranslationOptions;
use BrunosCode\TwillTranslationHandler\DatabaseHandler;

it('publishes every option the core config defines', function () {
    $twill = require __DIR__.'/../config/translation-handler.php';
    $core = require __DIR__.'/../vendor/brunoscode/laravel-translation-handler/config/translation-handler.php';

    expect(array_diff(array_keys($core), array_keys($twill)))->toBe([]);
});

it('keeps the twill defaults in the published config', function () {
    $twill = require __DIR__.'/../config/translation-handler.php';

    expect($twill['dbHandlerClass'])->toBe(DatabaseHandler::class)
        ->and($twill['defaultImportTo'])->toBe(TranslationOptions::DB)
        ->and($twill['defaultExportFrom'])->toBe(TranslationOptions::DB)
        ->and($twill['legacy-twill-navigation'])->toBeTrue()
        ->and($twill['fileNames'])->not->toContain('test');
});
