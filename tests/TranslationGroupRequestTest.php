<?php

use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Http\Requests\TranslationGroupRequest;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\Translation;
use Illuminate\Support\Facades\Validator;

function validateGroupSave(array $input): Illuminate\Validation\Validator
{
    $request = TranslationGroupRequest::create('/admin/translations/translationGroups/1', 'PUT', $input);
    $validator = Validator::make($request->all(), $request->rulesForUpdate());
    $request->withValidator($validator);
    $validator->passes();

    return $validator;
}

it('accepts a save that keeps the lang file consistent', function () {
    $t = Translation::create(['key' => 'test.a']);
    $t->translations()->create(['locale' => 'en', 'value' => 'A', 'active' => true]);

    expect(validateGroupSave(['trans_'.$t->id => ['en' => 'A2', 'it' => 'A2']])->errors()->all())->toBe([]);
});

it('rejects a save into a lang file that holds a key both as a value and a parent', function () {
    $a = Translation::create(['key' => 'test.a']);
    $a->translations()->create(['locale' => 'en', 'value' => 'A', 'active' => true]);
    Translation::create(['key' => 'test.a.b'])
        ->translations()->create(['locale' => 'en', 'value' => 'B', 'active' => true]);

    $errors = validateGroupSave(['trans_'.$a->id => ['en' => 'A2']])->errors();

    expect($errors->has('prefix'))->toBeTrue()
        ->and($errors->first('prefix'))->toContain('test.a.b');
});

it('rejects a value for a locale the translation handler does not export', function () {
    $t = Translation::create(['key' => 'test.a']);

    $errors = validateGroupSave(['trans_'.$t->id => ['en' => 'A', 'fr' => 'A']])->errors();

    expect($errors->keys())->toBe(['trans_'.$t->id.'.fr']);
});
