i18next-php
===================
PHP class for basic [i18next](https://github.com/jamuhl/i18next) functionality.

## Features

- Support for [variables](http://i18next.com/pages/doc_features.html#interpolation)
- Support for [basic plural forms](http://i18next.com/pages/doc_features.html#plurals)
- Support for fallback languages
- Simple directory-based language file structure

## Usage

```php
// Create i18next instance with primary language and translation directory
$i18n = new i18next('en', 'translations/');

// Get translation by key
echo $i18n->getTranslation('animal.dog');

// With fallback language
$i18n = new i18next('de', 'translations/', 'en');
```

## Methods

### __construct( string $language, string $path [, string $fallbackLanguage ] )
Creates a new i18next instance and loads translation files from the given directory.
```php
$i18n = new i18next('en', 'translations/');
// loads translations/en.json

$i18n = new i18next('de', 'translations/', 'en');
// loads translations/de.json with fallback to translations/en.json
```

The translation directory should contain JSON files named after language codes (e.g., `en.json`, `de.json`, `fi.json`).

Method throws an exception if the directory is not found or the JSON files cannot be parsed.

### string getTranslation( string $key [, array $variables ] )
Returns translated string by key.
```php
$i18n->getTranslation('animal.catWithCount', ['count' => 2]);
$i18n->getTranslation('welcome', ['name' => 'John']);
```

Variables array supports:
- `count`: For plural forms
- `defaultValue`: Default value if translation not found
- Any custom variables for interpolation

## Language Files

Translation files should be JSON files in the translations directory:

```
translations/
  en.json
  de.json
  fi.json
```

Example `en.json`:
```json
{
  "animal": {
    "dog": "dog",
    "dog_2": "dogs",
    "cat": "cat"
  },
  "welcome": "Welcome {{name}}!",
  "item": "item",
  "item_2": "items"
}
```

