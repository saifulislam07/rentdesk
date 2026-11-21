<?php

namespace App\Services;

use App\Models\Language;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

/**
 * The languages administrators offer. Their names and text direction live in the database; their translations
 * are JSON files in storage/app/lang, loaded after (and so over) the ones shipped in lang/.
 */
class Languages
{
    private const CACHE_KEY = 'rentdesk.languages';

    private const MAX_ENTRIES = 5000;

    /**
     * Make the active languages the ones the language switcher, validation and the page direction use.
     */
    public function apply(): void
    {
        $languages = Cache::rememberForever(self::CACHE_KEY, fn (): array => Language::query()
            ->where('is_active', true)
            ->orderByRaw('code = ? desc', [Language::BASE])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Language $language): array => [$language->code => ['name' => $language->name, 'dir' => $language->dir]])
            ->all());

        if ($languages !== []) {
            config(['rentdesk.locales' => $languages]);
        }
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Load the translation files administrators uploaded.
     */
    public function registerTranslationPath(): void
    {
        app('translator')->getLoader()->addPath($this->directory());
    }

    public function directory(): string
    {
        return storage_path('app/lang');
    }

    /**
     * Every string the application can show, which is what a translation has to cover.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(json_decode(file_get_contents(lang_path('bn.json')), true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * The strings of a language as a file to edit: its translations, with English wherever there is none yet.
     *
     * @return array<string, string>
     */
    public function export(Language $language): array
    {
        $translations = $language->isBase() ? [] : $this->translations($language->code);

        return collect($this->keys())->mapWithKeys(fn (string $key): array => [$key => $translations[$key] ?? $key])->all();
    }

    /**
     * @return array<string, string>
     */
    public function translations(string $code): array
    {
        $translations = [];

        foreach ([lang_path("{$code}.json"), $this->path($code)] as $file) {
            if (is_file($file)) {
                $translations = array_replace($translations, json_decode(file_get_contents($file), true) ?: []);
            }
        }

        return $translations;
    }

    /**
     * How many of the application's strings have a translation of their own.
     *
     * @return array{translated: int, total: int}
     */
    public function progress(Language $language): array
    {
        $keys = $this->keys();

        if ($language->isBase()) {
            return ['translated' => count($keys), 'total' => count($keys)];
        }

        $translations = $this->translations($language->code);
        $translated = count(array_filter($keys, fn (string $key): bool => ($translations[$key] ?? '') !== '' && $translations[$key] !== $key));

        return ['translated' => $translated, 'total' => count($keys)];
    }

    /**
     * Check an uploaded translation file and save it for the language.
     *
     * @throws ValidationException
     */
    public function import(Language $language, UploadedFile $file): int
    {
        $entries = $this->parse((string) file_get_contents($file->getRealPath()));

        File::ensureDirectoryExists($this->directory());
        file_put_contents($this->path($language->code), json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n");

        return count($entries);
    }

    public function remove(Language $language): void
    {
        File::delete($this->path($language->code));
    }

    /**
     * Keep the entries that translate a known string and carry its placeholders; reject the file when one does not.
     *
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    private function parse(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded) || array_is_list($decoded) || count($decoded) > self::MAX_ENTRIES) {
            throw ValidationException::withMessages(['translations' => __('The file must be a JSON object of "English text": "translation" pairs.')]);
        }

        $known = array_flip($this->keys());
        $entries = [];
        $broken = [];

        foreach ($decoded as $english => $translation) {
            if (! isset($known[$english]) || ! is_string($translation) || trim($translation) === '' || $translation === $english) {
                continue;
            }

            if ($this->placeholders($english) !== $this->placeholders($translation) || substr_count($english, '|') !== substr_count($translation, '|')) {
                $broken[] = $english;

                continue;
            }

            $entries[$english] = $translation;
        }

        if ($broken !== []) {
            throw ValidationException::withMessages(['translations' => __('Placeholders or plural forms differ from the English text in: :strings', ['strings' => implode(' | ', array_slice($broken, 0, 5))])]);
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $text): array
    {
        preg_match_all('/:[a-zA-Z_]+/', $text, $matches);
        $found = array_unique($matches[0]);
        sort($found);

        return $found;
    }

    private function path(string $code): string
    {
        return $this->directory().DIRECTORY_SEPARATOR."{$code}.json";
    }
}
