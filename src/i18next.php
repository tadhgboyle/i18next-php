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
 */
class i18next
{
    private ?string $fallbackLanguage = null;
    private array $translations = [];
    private string $language;
    private ?string $path;

    /**
     * Initialize i18next class
     *
     * @param string $language Locale language code
     * @param string|null $path Path to locale json files
     * @param string|null $fallbackLanguage Optional fallback language code
     * @throws Exception If translation files cannot be loaded
     */
    public function __construct(
        string $language = 'en',
        ?string $path = null,
        ?string $fallbackLanguage = null
    ) {
        $this->language = $language;
        $this->path = $path;

        if ($fallbackLanguage) {
            $this->fallbackLanguage = $fallbackLanguage;
        }

        $this->loadTranslation();
    }

    /**
     * Get translation for given key
     *
     * @param string $key Key for the translation (supports dot notation for nested keys)
     * @param array $variables Variables for interpolation and modifiers (count, lng, defaultValue)
     * @return string The translated string with variables interpolated
     */
    public function getTranslation(string $key, array $variables = []): string
    {
        $translation = $this->getKey($key, $variables);

        // Try fallback language if no translation found and no explicit language specified
        if (!$translation && !isset($variables['lng']) && $this->fallbackLanguage) {
            $fallbackVariables = array_merge($variables, ['lng' => $this->fallbackLanguage]);
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
                $translation = preg_replace('/{{' . $variable . '}}/', (string) $value, $translation);
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
        $resolvedPath = $this->resolvePath();
        $files = $this->getTranslationFiles($resolvedPath);

        if (empty($files)) {
            throw new Exception('Translation file not found');
        }

        foreach ($files as $file) {
            $translationData = $this->loadTranslationFile($file);
            $this->mergeTranslationData($file, $translationData);
        }
    }

    /**
     * Resolve the translation file path and determine if namespaces are used
     *
     * @return array Array with 'path' and 'hasNamespaces' keys
     */
    private function resolvePath(): array
    {
        $path = preg_replace('/__(.+?)__/', '*', $this->path, 2, $hasNs);
        $hasNamespaces = $hasNs !== 0;

        if (!preg_match('/\.json$/', (string) $path)) {
            $path .= 'translation.json';
            $this->path .= 'translation.json';
        }

        return ['path' => $path, 'hasNamespaces' => $hasNamespaces];
    }

    /**
     * Get list of translation files from the resolved path
     *
     * @param array $pathInfo Path information with 'path' and 'hasNamespaces' keys
     * @return array Array of file paths
     */
    private function getTranslationFiles(array $pathInfo): array
    {
        return glob($pathInfo['path']);
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
     * Merge translation data into the translations array
     *
     * @param string $filePath Path to the translation file
     * @param array $translationData Parsed translation data
     */
    private function mergeTranslationData(string $filePath, array $translationData): void
    {
        $pathInfo = $this->resolvePath();

        if ($pathInfo['hasNamespaces']) {
            $this->mergeNamespacedTranslation($filePath, $translationData);
        } else {
            $this->mergeSimpleTranslation($translationData);
        }
    }

    /**
     * Merge translation data for namespaced translations
     *
     * @param string $filePath Path to the translation file
     * @param array $translationData Parsed translation data
     */
    private function mergeNamespacedTranslation(string $filePath, array $translationData): void
    {
        $regexp = preg_replace('/__(.+?)__/', '(?<$1>.+)?', preg_quote($this->path, '/'));
        preg_match('/^' . $regexp . '$/', $filePath, $matches);

        if (!array_key_exists('lng', $matches)) {
            $matches['lng'] = $this->language;
        }

        $language = $matches['lng'];

        if (array_key_exists('ns', $matches)) {
            $namespace = $matches['ns'];
            $this->mergeLanguageNamespace($language, $namespace, $translationData);
        } else {
            $this->mergeLanguageTranslation($language, $translationData);
        }
    }

    /**
     * Merge translation data for a specific language and namespace
     *
     * @param string $language Language code
     * @param string $namespace Namespace identifier
     * @param array $translationData Translation data to merge
     */
    private function mergeLanguageNamespace(string $language, string $namespace, array $translationData): void
    {
        if (isset($this->translations[$language][$namespace])) {
            $this->translations[$language][$namespace] = array_merge(
                $this->translations[$language][$namespace],
                [$namespace => $translationData]
            );
        } elseif (isset($this->translations[$language])) {
            $this->translations[$language] = array_merge(
                $this->translations[$language],
                [$namespace => $translationData]
            );
        } else {
            $this->translations[$language] = [$namespace => $translationData];
        }
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
     * Merge simple translation data (without language nesting)
     *
     * @param array $translationData Translation data to merge
     */
    private function mergeSimpleTranslation(array $translationData): void
    {
        if (array_key_exists($this->language, $translationData)) {
            $this->translations = $translationData;
        } else {
            $this->translations = array_merge($this->translations, $translationData);
        }
    }

    /**
     * Get translation value for a specific key
     *
     * @param string $key Translation key
     * @param array $variables Variables for modifiers and language selection
     * @return string|false The translation value or false if not found
     */
    private function getKey(string $key, array $variables = [])
    {
        $translation = $this->selectTranslationLanguage($variables);
        $result = $this->traverseTranslationPath($key, $translation, $variables);

        return $result;
    }

    /**
     * Select the appropriate translation language based on variables
     *
     * @param array $variables Variables that may contain language override
     * @return array Translation data for the selected language
     */
    private function selectTranslationLanguage(array $variables): array
    {
        if (array_key_exists('lng', $variables) && array_key_exists($variables['lng'], $this->translations)) {
            return $this->translations[$variables['lng']];
        }

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
