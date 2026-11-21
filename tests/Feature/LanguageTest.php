<?php

namespace Tests\Feature;

use App\Models\Language;
use App\Models\Setting;
use App\Models\User;
use App\Services\Languages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class LanguageTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> Translation files created by a test, removed afterwards. */
    private array $createdFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            File::delete($file);
        }

        parent::tearDown();
    }

    private function admin(): User
    {
        return $this->userWithRole('admin');
    }

    private function translationFile(string $code): string
    {
        return $this->createdFiles[] = app(Languages::class)->directory().DIRECTORY_SEPARATOR."{$code}.json";
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function upload(array $entries, string $name = 'fr.json'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, json_encode($entries, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return ['code' => 'fr', 'name' => 'Français', 'dir' => 'ltr', 'is_active' => '1', ...$overrides];
    }

    public function test_english_and_bengali_ship_with_the_app(): void
    {
        $this->assertSame(['bn', 'en'], Language::orderBy('code')->pluck('code')->all());
    }

    public function test_only_administrators_manage_languages(): void
    {
        foreach (['manager', 'staff'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('languages.index'))->assertForbidden();
            $this->actingAs($user)->post(route('languages.store'), $this->form())->assertForbidden();
            $this->actingAs($user)->get(route('languages.export', Language::first()))->assertForbidden();
        }

        $this->assertDatabaseMissing('languages', ['code' => 'fr']);
    }

    public function test_the_list_shows_every_language_with_its_progress(): void
    {
        $this->actingAs($this->admin())->get(route('languages.index'))
            ->assertOk()
            ->assertSee('English')
            ->assertSee('বাংলা')
            ->assertSee('Default');
    }

    public function test_an_administrator_adds_a_language_and_it_appears_in_the_menu(): void
    {
        $this->actingAs($this->admin())->post(route('languages.store'), $this->form())->assertRedirect(route('languages.index'))->assertSessionHas('status');

        $this->assertDatabaseHas('languages', ['code' => 'fr', 'name' => 'Français', 'dir' => 'ltr', 'is_active' => true]);

        auth()->logout();
        $this->get(route('login'))->assertSee('Français');
    }

    public function test_a_right_to_left_language_flips_the_page(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('languages.store'), $this->form(['code' => 'ar', 'name' => 'العربية', 'dir' => 'rtl']));

        $this->withSession(['locale' => 'ar'])->actingAs($admin)->get(route('dashboard'))->assertSee('<html lang="ar" dir="rtl">', false);
    }

    public function test_a_translation_file_is_imported_and_used(): void
    {
        $this->translationFile('fr');
        $upload = $this->upload(['Dashboard' => 'Tableau de bord', 'Inventory' => 'Inventaire']);

        $this->actingAs($this->admin())->post(route('languages.store'), $this->form(['translations' => $upload]))
            ->assertSessionHas('status', fn (string $status): bool => str_contains($status, '2'));

        $this->withSession(['locale' => 'fr'])->actingAs($this->userWithRole('staff'))->get(route('dashboard'))
            ->assertSee('Tableau de bord')
            ->assertSee('Inventaire')
            // Strings without a translation stay in English.
            ->assertSee('Rentals');
    }

    public function test_the_file_may_hold_only_known_strings_with_matching_placeholders(): void
    {
        $this->translationFile('fr');

        $this->actingAs($this->admin())->post(route('languages.store'), $this->form(['translations' => $this->upload([
            'Dashboard' => 'Tableau de bord',
            'Not a string of the app' => 'Ignoré',
            'Hello :name,' => 'Bonjour,',
        ])]))->assertSessionHasErrors('translations');

        $this->assertDatabaseMissing('languages', ['code' => 'fr']);
        $this->assertFileDoesNotExist($this->translationFile('fr'));
    }

    public function test_unknown_strings_in_the_file_are_dropped(): void
    {
        $this->translationFile('fr');

        $this->actingAs($this->admin())->post(route('languages.store'), $this->form(['translations' => $this->upload([
            'Dashboard' => 'Tableau de bord',
            'Not a string of the app' => 'Ignoré',
        ])]));

        $saved = json_decode(file_get_contents($this->translationFile('fr')), true);

        $this->assertSame(['Dashboard' => 'Tableau de bord'], $saved);
    }

    public function test_files_that_are_not_a_json_object_are_rejected(): void
    {
        $admin = $this->admin();

        foreach (['not json at all', '["a","b"]', '"text"', '{"Dashboard": ["x"]}x'] as $content) {
            $file = UploadedFile::fake()->createWithContent('fr.json', $content);

            $this->actingAs($admin)->post(route('languages.store'), $this->form(['translations' => $file]))->assertSessionHasErrors('translations');
        }

        $this->actingAs($admin)->post(route('languages.store'), $this->form(['translations' => UploadedFile::fake()->createWithContent('fr.php', '<?php echo 1;')]))->assertSessionHasErrors('translations');
        $this->assertDatabaseMissing('languages', ['code' => 'fr']);
    }

    public function test_the_code_and_the_other_fields_are_validated(): void
    {
        $admin = $this->admin();

        foreach ([
            ['code', ''], ['code', 'F'], ['code', 'french-language'], ['code', '../etc'], ['code', 'en'], ['code', 'bn'], ['code', 'fr/x'],
            ['name', ''], ['dir', 'up'],
        ] as [$field, $value]) {
            $this->actingAs($admin)->post(route('languages.store'), $this->form([$field => $value]))->assertSessionHasErrors($field);
        }

        $this->actingAs($admin)->post(route('languages.store'), $this->form(['code' => 'pt-BR']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('languages', ['code' => 'pt-BR']);
    }

    public function test_editing_changes_the_name_direction_and_status_but_never_the_code(): void
    {
        $language = Language::factory()->create(['code' => 'de', 'name' => 'Deutsch']);

        $this->actingAs($this->admin())->put(route('languages.update', $language), ['code' => 'xx', 'name' => 'Deutsch (DE)', 'dir' => 'rtl', 'is_active' => '0'])
            ->assertRedirect(route('languages.index'));

        $language->refresh();
        $this->assertSame('de', $language->code);
        $this->assertSame('Deutsch (DE)', $language->name);
        $this->assertSame('rtl', $language->dir);
        $this->assertFalse($language->is_active);

        auth()->logout();
        $this->get(route('login'))->assertOk()->assertSee('English')->assertDontSee('Deutsch');
    }

    public function test_the_base_and_default_languages_stay_active_and_cannot_be_removed(): void
    {
        $admin = $this->admin();
        $english = Language::firstWhere('code', 'en');

        $this->actingAs($admin)->put(route('languages.update', $english), ['name' => 'English', 'dir' => 'ltr', 'is_active' => '0']);
        $this->assertTrue($english->fresh()->is_active);

        $this->actingAs($admin)->delete(route('languages.destroy', $english))->assertSessionHas('error');
        $this->assertDatabaseHas('languages', ['code' => 'en']);

        $this->actingAs($admin)->put(route('settings.update'), [
            'company_name' => 'X', 'currency' => 'USD', 'tax_rate' => 0, 'invoice_due_days' => 7, 'late_fee_percent' => 100,
            'late_fee_grace_minutes' => 60, 'public_booking' => '0', 'locale' => 'bn', 'date_format' => 'Y-m-d', 'time_format' => 'H:i',
        ]);
        config(['app.locale' => 'en']);

        $this->actingAs($admin)->delete(route('languages.destroy', Language::firstWhere('code', 'bn')))->assertSessionHas('error');
        $this->assertDatabaseHas('languages', ['code' => 'bn']);
    }

    public function test_removing_a_language_deletes_its_translation_file(): void
    {
        $this->translationFile('fr');
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('languages.store'), $this->form(['translations' => $this->upload(['Dashboard' => 'Tableau de bord'])]));
        $this->assertFileExists($this->translationFile('fr'));

        $this->actingAs($admin)->delete(route('languages.destroy', Language::firstWhere('code', 'fr')))->assertRedirect(route('languages.index'));

        $this->assertDatabaseMissing('languages', ['code' => 'fr']);
        $this->assertFileDoesNotExist($this->translationFile('fr'));
        $this->post(route('locale'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
    }

    public function test_the_default_language_is_chosen_from_the_active_languages_only(): void
    {
        $admin = $this->admin();
        Language::factory()->inactive()->create(['code' => 'de', 'name' => 'Deutsch']);
        $settings = [
            'company_name' => 'X', 'currency' => 'USD', 'tax_rate' => 0, 'invoice_due_days' => 7, 'late_fee_percent' => 100,
            'late_fee_grace_minutes' => 60, 'public_booking' => '0', 'date_format' => 'Y-m-d', 'time_format' => 'H:i',
        ];

        $this->actingAs($admin)->put(route('settings.update'), [...$settings, 'locale' => 'de'])->assertSessionHasErrors('locale');

        Language::where('code', 'de')->update(['is_active' => true]);
        app(Languages::class)->forget();
        $this->actingAs($admin)->put(route('settings.update'), [...$settings, 'locale' => 'de'])->assertSessionHasNoErrors();
    }

    public function test_a_default_language_that_is_gone_falls_back_to_english(): void
    {
        Setting::create(['key' => 'locale', 'value' => 'zz']);

        $this->actingAs($this->userWithRole('staff'))->get(route('dashboard'))->assertSee('<html lang="en"', false);
    }

    public function test_the_download_lists_every_string_with_english_where_untranslated(): void
    {
        $this->translationFile('fr');
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('languages.store'), $this->form(['translations' => $this->upload(['Dashboard' => 'Tableau de bord'])]));

        $download = $this->actingAs($admin)->get(route('languages.export', Language::firstWhere('code', 'fr')));

        $download->assertOk()->assertDownload('fr.json');
        $strings = json_decode($download->streamedContent(), true);

        $this->assertSame('Tableau de bord', $strings['Dashboard']);
        $this->assertSame('Inventory', $strings['Inventory']);
        $this->assertCount(count(app(Languages::class)->keys()), $strings);
    }

    public function test_the_downloaded_file_can_be_uploaded_again_unchanged(): void
    {
        $this->translationFile('fr');
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('languages.store'), $this->form());
        $contents = $this->actingAs($admin)->get(route('languages.export', Language::firstWhere('code', 'fr')))->streamedContent();

        $this->actingAs($admin)->put(route('languages.update', Language::firstWhere('code', 'fr')), [
            'name' => 'Français', 'dir' => 'ltr', 'is_active' => '1', 'translations' => UploadedFile::fake()->createWithContent('fr.json', $contents),
        ])->assertSessionHasNoErrors();

        $this->assertSame([], json_decode(file_get_contents($this->translationFile('fr')), true));
    }

    public function test_progress_counts_translated_strings(): void
    {
        $this->translationFile('fr');
        $this->actingAs($this->admin())->post(route('languages.store'), $this->form(['translations' => $this->upload(['Dashboard' => 'Tableau de bord', 'Inventory' => 'Inventaire'])]));

        $languages = app(Languages::class);
        $total = count($languages->keys());

        $this->assertSame(['translated' => 2, 'total' => $total], $languages->progress(Language::firstWhere('code', 'fr')));
        $this->assertSame(['translated' => $total, 'total' => $total], $languages->progress(Language::firstWhere('code', 'en')));
        // Terms such as "SKU" read the same in Bengali and count as untranslated.
        $this->assertGreaterThan($total - 5, $languages->progress(Language::firstWhere('code', 'bn'))['translated']);
    }

    public function test_an_uploaded_file_can_improve_a_shipped_language(): void
    {
        $this->translationFile('bn');

        $this->actingAs($this->admin())->put(route('languages.update', Language::firstWhere('code', 'bn')), [
            'name' => 'বাংলা', 'dir' => 'ltr', 'is_active' => '1', 'translations' => $this->upload(['Dashboard' => 'নিয়ন্ত্রণ কক্ষ'], 'bn.json'),
        ])->assertSessionHasNoErrors();

        $this->assertSame('নিয়ন্ত্রণ কক্ষ', Lang::get('Dashboard', [], 'bn'));
    }

    public function test_uploaded_translations_are_escaped_on_the_page(): void
    {
        $this->translationFile('fr');
        $this->actingAs($this->admin())->post(route('languages.store'), $this->form(['translations' => $this->upload(['Dashboard' => '<script>alert(1)</script>'])]));

        $this->withSession(['locale' => 'fr'])->actingAs($this->userWithRole('staff'))->get(route('dashboard'))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
