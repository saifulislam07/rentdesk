<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Guards the Bengali translation file: every string the app can show must have a translation
 * with the same placeholders, and nothing stale should be left behind.
 */
class TranslationTest extends TestCase
{
    /**
     * Every literal passed to __() or trans_choice() in the application code.
     *
     * @return list<string>
     */
    private function usedStrings(): array
    {
        $finder = (new Finder)->files()->name('*.php')->in([base_path('app'), base_path('resources/views'), base_path('routes'), base_path('database/seeders')]);
        $pattern = "/(?:__|trans_choice)\\(\\s*(?:'((?:[^'\\\\\\\\]|\\\\\\\\.)*)'|\"((?:[^\"\\\\\\\\]|\\\\\\\\.)*)\")/s";
        $strings = [];

        foreach ($finder as $file) {
            if (preg_match_all($pattern, $file->getContents(), $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $text = stripcslashes($match[1] !== '' ? $match[1] : ($match[2] ?? ''));

                    if ($text !== '') {
                        $strings[$text] = true;
                    }
                }
            }
        }

        return array_keys($strings);
    }

    /**
     * @return array<string, string>
     */
    private function bengali(): array
    {
        $translations = json_decode(file_get_contents(lang_path('bn.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($translations);

        return $translations;
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

    public function test_every_string_in_the_code_has_a_bengali_translation(): void
    {
        $missing = array_values(array_diff($this->usedStrings(), array_keys($this->bengali())));

        $this->assertSame([], $missing, 'Strings missing from lang/bn.json: '.implode(' | ', $missing));
    }

    public function test_translations_keep_the_placeholders_of_the_original(): void
    {
        $broken = [];

        foreach ($this->bengali() as $english => $bengali) {
            if ($this->placeholders($english) !== $this->placeholders($bengali)) {
                $broken[] = $english;
            }
        }

        $this->assertSame([], $broken, 'Placeholders differ for: '.implode(' | ', $broken));
    }

    public function test_plural_translations_have_as_many_forms_as_the_original(): void
    {
        foreach ($this->bengali() as $english => $bengali) {
            $this->assertSame(substr_count($english, '|'), substr_count($bengali, '|'), "Plural forms differ for: {$english}");
        }
    }

    public function test_no_translations_are_left_over_for_strings_that_no_longer_exist(): void
    {
        $stale = array_values(array_diff(array_keys($this->bengali()), $this->usedStrings()));

        $this->assertSame([], $stale, 'Unused entries in lang/bn.json: '.implode(' | ', $stale));
    }

    public function test_no_translation_is_empty_or_left_in_english(): void
    {
        foreach ($this->bengali() as $english => $bengali) {
            $this->assertNotSame('', trim($bengali), "Empty translation for: {$english}");
        }
    }

    public function test_the_application_returns_bengali_text_and_falls_back_to_english_for_unknown_strings(): void
    {
        app()->setLocale('bn');

        $this->assertSame('ড্যাশবোর্ড', __('Dashboard'));
        $this->assertSame('হ্যালো রহিম,', __('Hello :name,', ['name' => 'রহিম']));
        $this->assertSame('3 days', __('3 days'));
    }

    public function test_plural_strings_are_translated_in_both_forms(): void
    {
        app()->setLocale('bn');

        $this->assertSame('1 টি ইউনিট যোগ করা হয়েছে।', trans_choice(':count unit added.|:count units added.', 1));
        $this->assertSame('5 টি ইউনিট যোগ করা হয়েছে।', trans_choice(':count unit added.|:count units added.', 5));
    }
}
