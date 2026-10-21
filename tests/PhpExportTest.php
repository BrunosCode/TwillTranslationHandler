<?php

use BrunosCode\TranslationHandler\Facades\TranslationHandler;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\Translation;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\TranslationGroup;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Repositories\TranslationGroupRepository;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Repositories\TranslationRepository;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;

function makeTranslation(string $key, array $values): Translation
{
    $translation = Translation::create(['key' => $key, 'published' => true]);

    foreach ($values as $locale => $value) {
        $translation->translations()->create(['locale' => $locale, 'value' => $value, 'active' => true]);
    }

    return $translation;
}

beforeEach(function () {
    $this->tmpDir = sys_get_temp_dir().'/th_php_'.uniqid();
    File::ensureDirectoryExists($this->tmpDir);

    config(['translation-handler.phpPath' => $this->tmpDir]);
});

afterEach(function () {
    File::deleteDirectory($this->tmpDir);
});

it('exports a top-level group into its php file', function () {
    $translation = makeTranslation('auth.failed', ['en' => 'Failed', 'it' => 'Fallito']);
    $group = TranslationGroup::create(['prefix' => 'auth']);

    app(TranslationGroupRepository::class)->update($group->id, [
        'prefix' => 'auth',
        'published' => true,
        'trans_'.$translation->id => ['en' => 'Login failed', 'it' => 'Accesso fallito'],
    ]);

    expect(require $this->tmpDir.'/en/auth.php')->toBe(['failed' => 'Login failed']);
});

it('exports a nested group into the top-level php file', function () {
    makeTranslation('auth.failed', ['en' => 'Failed', 'it' => 'Fallito']);
    $translation = makeTranslation('auth.messages.hello', ['en' => 'Hello', 'it' => 'Ciao']);
    $group = TranslationGroup::create(['prefix' => 'auth.messages']);

    app(TranslationGroupRepository::class)->update($group->id, [
        'prefix' => 'auth.messages',
        'published' => true,
        'trans_'.$translation->id => ['en' => 'Hi', 'it' => 'Salve'],
    ]);

    expect(File::exists($this->tmpDir.'/en/auth.messages.php'))->toBeFalse()
        ->and(File::exists($this->tmpDir.'/en/auth.php'))->toBeTrue()
        ->and(require $this->tmpDir.'/en/auth.php')->toBe([
            'failed' => 'Failed',
            'messages' => ['hello' => 'Hi'],
        ]);
});

it('reports a failed php export after saving a key', function () {
    Exceptions::fake();

    $translation = makeTranslation('auth.a', ['en' => 'A', 'it' => 'A']);
    makeTranslation('auth.a.b', ['en' => 'B', 'it' => 'B']);

    app(TranslationRepository::class)->update($translation->id, [
        'key' => 'auth.a',
        'published' => true,
        'value' => ['en' => 'A2', 'it' => 'A2'],
    ]);

    Exceptions::assertReported(InvalidArgumentException::class);
    expect(TranslationHandler::getOption('fileNames'))->toBe(['test']);
});

it('reports a failed php export after saving a group instead of failing the request', function () {
    Exceptions::fake();

    $translation = makeTranslation('auth.a', ['en' => 'A', 'it' => 'A']);
    makeTranslation('auth.a.b', ['en' => 'B', 'it' => 'B']);
    $group = TranslationGroup::create(['prefix' => 'auth']);

    app(TranslationGroupRepository::class)->update($group->id, [
        'prefix' => 'auth',
        'published' => true,
        'trans_'.$translation->id => ['en' => 'A2', 'it' => 'A2'],
    ]);

    Exceptions::assertReported(InvalidArgumentException::class);
    expect($translation->translations()->where('locale', 'en')->value('value'))->toBe('A2')
        ->and(TranslationHandler::getOption('fileNames'))->toBe(['test']);
});
