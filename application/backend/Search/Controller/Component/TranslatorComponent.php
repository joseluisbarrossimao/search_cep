<?php

namespace Search\Controller\Component;

use Search\Controller\BaseController;
use Search\Controller\Component;
use Search\Error\Exceptions;
use Search\Utility\Translator;

/**
 *
 */
class TranslatorComponent extends Component
{
    /**
     * @var Translator
     */
    private Translator $translator;

    /**
     * @param BaseController $controller
     */
    public function __construct(BaseController $controller)
    {
        $instance = $controller->instance();
        $apiKey = $_ENV['APP_GOOGLE_KEY'];
        parent::__construct($controller);
        if ($apiKey !== '' && $apiKey !== 'apiKey') {
            $this->translator = $instance->resolveClass(
                Search_NAMESPACE[0] . DS_REVERSE . 'Utility' . DS_REVERSE . 'Translator',
                ['instance' => $instance, 'key' => $apiKey]
            );
        }
        $this->lenguageDefine(['en', (string)($_ENV['APP_GOOGLE_LANGUAGE'] ?? '')]);
    }

    /**
     * @param string $key
     * @param array<string,string> $types
     * @return $this
     * @throws Exceptions
     */
    public function insertCaches(string $key, array $types = ['plural' => '', 'singular' => '']): TranslatorComponent
    {
        if (count($types) === 0 || ($types['plural'] === '' && $types['singular'] === '') || count($types) > 2) {
            throw new Exceptions('You must define at least one of the plural or singular types to cache.');
        }
        $this->translator->caches($key, $types['plural'], $types['singular']);
        return $this;
    }

    /**
     * @param array<string,string> $lenguage
     * @return $this
     */
    public function lenguageDefine(array $lenguage): TranslatorComponent
    {
        if (count($lenguage) === 0 || count($lenguage) >= 3) {
            throw new Exceptions('You must define at least one language to translate.',404);
        }
        if (count($lenguage) === 1) {
            if ($lenguage[0] === null || $lenguage[0] === '') {
                throw new Exceptions('The source language cannot be null or empty.', 404);
            }
            $lenguage = array_merge(['en'], $lenguage);
        } else {
            if ($lenguage[0] === null || $lenguage[1] === null || $lenguage[0] === '' || $lenguage[1] === '') {
                throw new Exceptions('The source language cannot be null or empty.', 404);
            }
        }
        $this->translator->lenguageDefine($lenguage);
        return $this;
    }

    /**
     * @param string $text
     * @param array<string,mixed> $options
     * @return string
     * @throws Exceptions
     */
    public function translateSingule(string $text, array $options = []): string
    {
        return $this->translator->singular($text, $options['translate'] ?? false, $options['convert'] ?? '');
    }

    /**
     * @param array $text
     * @param array<string,mixed> $options
     * @return array
     * @throws Exceptions
     */
    public function translateArraySingule(array $text, array $options = []): array
    {
        return $this->translator->singular($text, $options['translate'] ?? false, $options['convert'] ?? '');
    }

    /**
     * @param string $text
     * @param array<string,mixed> $options
     * @return string
     * @throws Exceptions
     */
    public function translatePlural(string $text, array $options = []): string
    {
        return $this->translator->plural($text, $options['translate'] ?? false, $options['convert'] ?? '');
    }
    /**
     * @param array $text
     * @param array<string,mixed> $options
     * @return array
     * @throws Exceptions
     */
    public function translateArrayPlural(array $text, array $options = []): array
    {
        return $this->translator->plural($text, $options['translate'] ?? false, $options['convert'] ?? '');
    }

    /**
     * @param string $text
     * @param string $convert
     * @return string
     * @throws Exceptions
     */
    public function translateGoogle(string $text, string $convert = ''): string
    {
        return $this->translator->translation($text, $convert);
    }
}
