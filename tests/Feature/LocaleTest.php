<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Language;
use App\Services\DocumentRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_choose_a_language_without_signing_in(): void
    {
        $this->from(route('login'))
            ->post(route('locale'), ['locale' => 'bn'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('locale', 'bn');
    }

    public function test_only_offered_languages_can_be_chosen(): void
    {
        $this->post(route('locale'), ['locale' => 'xx'])->assertSessionHasErrors('locale');
        $this->post(route('locale'), [])->assertSessionHasErrors('locale');
    }

    public function test_the_chosen_language_is_used_on_every_page(): void
    {
        $staff = $this->userWithRole('staff');

        $this->withSession(['locale' => 'bn'])->actingAs($staff)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<html lang="bn" dir="ltr">', false)
            ->assertSee('ড্যাশবোর্ড')
            ->assertSee('ইনভেন্টরি');
    }

    public function test_the_login_page_is_translated_too(): void
    {
        $this->withSession(['locale' => 'bn'])->get(route('login'))->assertOk()->assertSee('লগ ইন');
    }

    public function test_the_default_language_is_english_and_unknown_choices_are_ignored(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get(route('dashboard'))->assertSee('<html lang="en" dir="ltr">', false)->assertSee('Inventory');
        $this->withSession(['locale' => 'xx'])->actingAs($staff)->get(route('dashboard'))->assertSee('Inventory');
    }

    public function test_the_language_switcher_lists_every_language(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('English')->assertSee('বাংলা')->assertSee('name="locale"', false);
    }

    public function test_the_public_booking_page_follows_the_language(): void
    {
        config(['rentdesk.public_booking' => true]);

        $this->withSession(['locale' => 'bn'])->get(route('book.create'))->assertOk()->assertSee('বুকিংয়ের অনুরোধ করুন');
    }

    public function test_a_right_to_left_language_flips_the_page_direction(): void
    {
        Language::factory()->rtl()->create(['code' => 'ar', 'name' => 'العربية']);

        $page = $this->withSession(['locale' => 'ar'])->actingAs($this->userWithRole('staff'))->get(route('dashboard'));

        $page->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            // Strings without an Arabic translation fall back to English instead of breaking.
            ->assertSee('Inventory')
            ->assertSee('rtl:translate-x-full', false);
    }

    public function test_pdfs_stay_in_the_pdf_language_and_the_users_language_is_restored(): void
    {
        $invoice = Invoice::factory()->create();
        $invoice->items()->create(['description' => 'Hammer Drill', 'qty' => 1, 'price' => 50]);
        app()->setLocale('bn');

        $html = app(DocumentRenderer::class)->invoice($invoice)->getDomPDF()->outputHtml();

        $this->assertStringContainsString('Billed to', $html);
        $this->assertStringNotContainsString('ইনভয়েস', $html);
        $this->assertSame('bn', app()->getLocale());
    }

    public function test_the_views_use_direction_neutral_spacing_and_alignment(): void
    {
        $physical = '/(?<![\w-])(?:text-(?:left|right)|(?:ml|mr|pl|pr)-\S|border-[lr](?:-\d)?(?![\w-])|(?:left|right)-(?:\d|\[|px))/';
        $offenders = [];

        foreach ((new Finder)->files()->name('*.blade.php')->in(resource_path('views')) as $file) {
            if (str_contains($file->getRelativePathname(), 'pdf')) {
                continue;
            }

            if (preg_match_all($physical, $file->getContents(), $matches)) {
                $offenders[$file->getRelativePathname()] = array_unique($matches[0]);
            }
        }

        $this->assertSame([], $offenders, 'Use start/end utilities (ms-, ps-, text-start, start-0 ...) so right-to-left languages work: '.json_encode($offenders));
    }
}
