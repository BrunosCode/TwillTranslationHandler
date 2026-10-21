<?php

use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Http\Controllers\TranslationGroupController;
use Illuminate\Http\Request;

it('offers only the locales the translation handler exports in the group form', function () {
    config([
        'translatable.locales' => ['en', 'it', 'fr'],
        'translation-handler.locales' => ['en', 'it'],
    ]);

    $controller = app(TranslationGroupController::class);
    $formData = (fn () => $this->formData(Request::create('/')))->call($controller);

    expect(array_column($formData['languages'], 'value'))->toBe(['en', 'it']);
});
