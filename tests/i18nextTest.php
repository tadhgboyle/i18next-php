<?php

namespace samerton\i18next\Test;

use Exception;
use PHPUnit\Framework\TestCase;
use samerton\i18next\i18next;

final class i18nextTest extends TestCase
{
    private i18next $i18n;

    public function setUp(): void
    {
        $this->i18n = new i18next('en', 'tests/fixtures/translations/');
    }

    public function testInitFail(): void
    {
        $this->expectException(Exception::class);

        new i18next('en', 'not found');
    }

    public function testBasics(): void
    {
        // Simple
        $this->assertSame('dog', $this->i18n->getTranslation('animal.dog'));
        $this->assertSame('A friend', $this->i18n->getTranslation('friend'));

        // With count
        $this->assertSame('1 cat', $this->i18n->getTranslation('animal.catWithCount', ['count' => 1]));

        // Fallsback to return key
        $this->assertSame('animal.gorilla', $this->i18n->getTranslation('animal.gorilla'));
    }

    public function testPlural(): void
    {
        // Simple plural
        $this->assertSame('dogs', $this->i18n->getTranslation('animal.dog', ['count' => 2]));
    }

    public function testModifiers(): void
    {
        // Plural with Finnish primary language
        $i18nFi = new i18next('fi', 'tests/fixtures/translations/');
        $this->assertSame('koiraa', $i18nFi->getTranslation('animal.dog', ['count' => 2]));
    }

    public function testFallbackLanguage(): void
    {
        $i18nWithFallback = new i18next('de', 'tests/fixtures/translations/', 'en');

        // Should fallback to English when German translation doesn't exist
        $this->assertSame('Welcome John!', $i18nWithFallback->getTranslation('welcome', ['name' => 'John']));
        $this->assertSame('item', $i18nWithFallback->getTranslation('item'));
        $this->assertSame('items', $i18nWithFallback->getTranslation('item', ['count' => 2]));
    }

    public function testVariableInterpolation(): void
    {
        // String interpolation
        $this->assertSame('Welcome John!', $this->i18n->getTranslation('welcome', ['name' => 'John']));
        $this->assertSame('Your score is 95', $this->i18n->getTranslation('score', ['score' => 95]));

        // Numeric values
        $this->assertSame('Your score is 100', $this->i18n->getTranslation('score', ['score' => 100]));

        // Multiple variables
        $translation = $this->i18n->getTranslation('welcome', ['name' => 'Jane', 'unused' => 'test']);
        $this->assertSame('Welcome Jane!', $translation);
    }

    public function testDeepNesting(): void
    {
        // Deep nested values
        $this->assertSame('Deep nested value', $this->i18n->getTranslation('nested.deep.value'));

        // Non-existent deep path
        $this->assertSame('nested.deep.nonexistent', $this->i18n->getTranslation('nested.deep.nonexistent'));
    }

    public function testEmptyAndSpecialValues(): void
    {
        // Empty string values will fall back to key because empty string is falsy
        $this->assertSame('empty', $this->i18n->getTranslation('empty'));

        // Empty key should return empty string
        $this->assertSame('', $this->i18n->getTranslation(''));

        // Non-existent key should return the key itself
        $this->assertSame('nonexistent', $this->i18n->getTranslation('nonexistent'));
    }

    public function testSpecificPluralCounts(): void
    {
        // Specific count plurals
        $this->assertSame('exactly 5 items', $this->i18n->getTranslation('item', ['count' => 5]));
        $this->assertSame('exactly 10 items', $this->i18n->getTranslation('item', ['count' => 10]));

        // Fallback to regular plural for unspecific counts
        $this->assertSame('items', $this->i18n->getTranslation('item', ['count' => 7]));
    }

    public function testLanguageOverride(): void
    {
        // Create Finnish instance for Finnish translations
        $i18nFi = new i18next('fi', 'tests/fixtures/translations/');
        $this->assertSame('Tervetuloa Maria!', $i18nFi->getTranslation('welcome', ['name' => 'Maria']));
        $this->assertSame('kohdetta', $i18nFi->getTranslation('item', ['count' => 2]));

        // Create German instance - if German translation doesn't exist, it should return key
        $i18nDe = new i18next('de', 'tests/fixtures/translations/');
        $this->assertSame('welcome', $i18nDe->getTranslation('welcome'));
    }

    public function testInvalidJsonFile(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid json');

        new i18next('invalid', 'tests/fixtures/translations');
    }

    public function testConstructorWithEmptyFallback(): void
    {
        // Test that empty string fallback doesn't set fallback language
        $i18n = new i18next('en', 'tests/fixtures/translations/', '');
        $this->assertSame('non.existent', $i18n->getTranslation('non.existent'));

        // Test that null fallback doesn't set fallback language
        $i18n = new i18next('en', 'tests/fixtures/translations/', null);
        $this->assertSame('non.existent', $i18n->getTranslation('non.existent'));
    }

    public function testVariableWithNonStringValues(): void
    {
        // Test that non-string, non-numeric values are not interpolated
        $result = $this->i18n->getTranslation('welcome', ['name' => ['array' => 'value']]);
        $this->assertSame('Welcome {{name}}!', $result); // Should not replace array values

        // Test with object
        $obj = new \stdClass();
        $obj->prop = 'value';
        $result = $this->i18n->getTranslation('welcome', ['name' => $obj]);
        $this->assertSame('Welcome {{name}}!', $result); // Should not replace object values
    }

    public function testConstructorPathVariations(): void
    {
        // Test with trailing slash
        $i18nWithSlash = new i18next('en', 'tests/fixtures/translations/');
        $this->assertSame('A friend', $i18nWithSlash->getTranslation('friend'));

        // Test without trailing slash
        $i18nWithoutSlash = new i18next('en', 'tests/fixtures/translations');
        $this->assertSame('A friend', $i18nWithoutSlash->getTranslation('friend'));
    }

    public function testComplexVariableInterpolation(): void
    {
        // Test with multiple variables and mixed types
        $result = $this->i18n->getTranslation('score', [
            'score' => 85.5,
            'unused' => 'ignored',
            'notstring' => ['ignored']
        ]);
        $this->assertSame('Your score is 85.5', $result);

        // Test with zero values
        $result = $this->i18n->getTranslation('score', ['score' => 0]);
        $this->assertSame('Your score is 0', $result);
    }

    public function testFallbackLanguageWithMissingTranslations(): void
    {
        // Create instance with German as primary and English as fallback
        $i18nFallback = new i18next('de', 'tests/fixtures/translations/', 'en');

        // German doesn't exist for 'welcome', should fallback to English
        $this->assertSame('Welcome User!', $i18nFallback->getTranslation('welcome', ['name' => 'User']));

        // Test that German-only instance returns key when translation doesn't exist
        $i18nDeOnly = new i18next('de', 'tests/fixtures/translations/');
        $this->assertSame('welcome', $i18nDeOnly->getTranslation('welcome'));
    }
}
