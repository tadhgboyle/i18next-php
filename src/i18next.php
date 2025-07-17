<?php

/*
 * ----------------------------------------------------------------------------
 * "THE BEER-WARE LICENSE" (Revision 42):
 * <github.com/Mika-> wrote this file. As long as you retain this notice you
 * can do whatever you want with this stuff. If we meet some day, and you think
 * this stuff is worth it, you can buy me a beer in return
 * ----------------------------------------------------------------------------
 */

namespace samerton\i18next;

use Exception;

/**
 * i18next internationalization library for PHP
 *
 * Loads language-specific JSON files from a directory structure like:
 * translations/
 * ├── en.json
 * ├── fi.json
 * ├── de.json
 * └── fr.json
 */
class i18next
{
    private array $translations = [];

    /**
     * Initialize i18next class
     *
     * @param string $language Locale language code
     * @param string $path Path to directory containing language json files (e.g., 'translations/')
     * @param string|null $fallbackLanguage Optional fallback language code
     * @throws Exception If translation files cannot be loaded
     */
    public function __construct(
        private string $language,
        private string $path,
        private ?string $fallbackLanguage = null,
    ) {
        $this->loadTranslation();
    }

    /**
     * Get translation for given key
     *
     * @param string $key Key for the translation (supports dot notation for nested keys)
     * @param array $variables Variables for interpolation and modifiers (count, defaultValue)
     * @return string The translated string with variables interpolated
     */
    public function getTranslation(string $key, array $variables = []): string
    {
        $translation = $this->getKey($key, $variables);

        // Try fallback language if no translation found and fallback is configured
        if (!$translation && $this->fallbackLanguage) {
            $fallbackVariables = array_merge($variables, ['_useFallback' => true]);
            $translation = $this->getKey($key, $fallbackVariables);
        }

        // Use default value if provided and no translation found
        if (!$translation && array_key_exists('defaultValue', $variables)) {
            $translation = $variables['defaultValue'];
        }

        // Fallback to key if no translation found
        if (!$translation) {
            $translation = $key;
        }

        // Interpolate variables into the translation
        return $this->interpolateVariables($translation, $variables);
    }

    /**
     * Interpolate variables into a translation string
     *
     * @param string $translation The translation string with placeholders
     * @param array $variables Array of variables to interpolate
     * @return string The translation with variables replaced
     */
    private function interpolateVariables(string $translation, array $variables): string
    {
        foreach ($variables as $variable => $value) {
            if (is_string($value) || is_numeric($value)) {
                $translation = preg_replace('/{{' . $variable . '}}/', (string)$value, $translation);
            }
        }

        return $translation;
    }

    /**
     * Load translation files from the specified path
     *
     * @throws Exception If translation files cannot be found or loaded
     */
    private function loadTranslation(): void
    {
        // Load primary language file
        $primaryFile = $this->getLanguageFilePath($this->language);
        if ($primaryFile) {
            $translationData = $this->loadTranslationFile($primaryFile);
            $this->mergeLanguageTranslation($this->language, $translationData);
        }

        // Load fallback language file if different from primary
        if ($this->fallbackLanguage && $this->fallbackLanguage !== $this->language) {
            $fallbackFile = $this->getLanguageFilePath($this->fallbackLanguage);
            if ($fallbackFile) {
                $translationData = $this->loadTranslationFile($fallbackFile);
                $this->mergeLanguageTranslation($this->fallbackLanguage, $translationData);
            }
        }

        // Check if we have any translations loaded
        if (empty($this->translations)) {
            throw new Exception('Translation file not found');
        }
    }

    /**
     * Get the file path for a specific language
     *
     * @param string $language Language code
     * @return string|null The file path or null if not found
     */
    private function getLanguageFilePath(string $language): ?string
    {
        // Handle directory with language-specific files like en.json, fi.json
        $filePath = rtrim($this->path, '/') . '/' . $language . '.json';
        return file_exists($filePath) ? $filePath : null;
    }

    /**
     * Load and parse a translation file
     *
     * @param string $filePath Path to the translation file
     * @return array Parsed translation data
     * @throws Exception If file cannot be read or contains invalid JSON
     */
    private function loadTranslationFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception("Could not read translation file: $filePath");
        }

        $translation = json_decode($content, true);
        if ($translation === null) {
            throw new Exception("Invalid json $filePath");
        }

        return $translation;
    }

    /**
     * Merge translation data for a specific language
     *
     * @param string $language Language code
     * @param array $translationData Translation data to merge
     */
    private function mergeLanguageTranslation(string $language, array $translationData): void
    {
        if (isset($this->translations[$language])) {
            $this->translations[$language] = array_merge($this->translations[$language], $translationData);
        } else {
            $this->translations[$language] = $translationData;
        }
    }

    /**
     * Get translation value for a specific key
     *
     * @param string $key Translation key
     * @param array $variables Variables for modifiers and internal flags
     * @return string|false The translation value or false if not found
     */
    private function getKey(string $key, array $variables = [])
    {
        $translation = $this->selectTranslationLanguage($variables);
        $result = $this->traverseTranslationPath($key, $translation, $variables);

        return $result;
    }

    /**
     * Select the appropriate translation language based on internal flags
     *
     * @param array $variables Variables that may contain internal flags
     * @return array Translation data for the selected language
     */
    private function selectTranslationLanguage(array $variables): array
    {
        // Use fallback language if explicitly requested
        if (
            array_key_exists('_useFallback', $variables) &&
            $this->fallbackLanguage &&
            array_key_exists($this->fallbackLanguage, $this->translations)
        ) {
            return $this->translations[$this->fallbackLanguage];
        }

        // Use primary language
        if (array_key_exists($this->language, $this->translations)) {
            return $this->translations[$this->language];
        }

        return $this->translations;
    }

    /**
     * Traverse the translation path using dot notation
     *
     * @param string $key Translation key with dot notation
     * @param array $translation Translation data to traverse
     * @param array $variables Variables for modifiers
     * @return string|false The translation value or false if not found
     */
    private function traverseTranslationPath(string $key, array $translation, array $variables)
    {
        $pathSegments = explode('.', $key);

        while ($segment = array_shift($pathSegments)) {
            if ($this->shouldContinueTraversal($segment, $translation, $pathSegments)) {
                $translation = $translation[$segment];
            } elseif (array_key_exists($segment, $translation)) {
                $resolvedKey = $this->applyPluralizationModifier($segment, $translation, $variables);
                return $translation[$resolvedKey];
            } else {
                return false;
            }
        }

        return false;
    }

    /**
     * Determine if traversal should continue to the next path segment
     *
     * @param string $segment Current path segment
     * @param array $translation Current translation data
     * @param array $remainingSegments Remaining path segments
     * @return bool True if traversal should continue
     */
    private function shouldContinueTraversal(string $segment, array $translation, array $remainingSegments): bool
    {
        return array_key_exists($segment, $translation)
            && is_array($translation[$segment])
            && count($remainingSegments) > 0;
    }

    /**
     * Apply pluralization modifier to a translation key
     *
     * @param string $key Base translation key
     * @param array $translation Translation data
     * @param array $variables Variables that may contain count
     * @return string Modified key with pluralization applied
     */
    private function applyPluralizationModifier(string $key, array $translation, array $variables): string
    {
        if (!array_key_exists('count', $variables) || $variables['count'] == 1) {
            return $key;
        }

        $specificPluralKey = $key . '_plural_' . $variables['count'];
        if (array_key_exists($specificPluralKey, $translation)) {
            return $specificPluralKey;
        }

        $generalPluralKey = $key . '_plural';
        if (array_key_exists($generalPluralKey, $translation)) {
            return $generalPluralKey;
        }

        return $key;
    }
}
