<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Setting;
use App\Services\DocumentRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'company_name' => 'Acme Rentals',
            'currency' => 'bdt',
            'tax_rate' => '7.5',
            'invoice_due_days' => 14,
            'late_fee_percent' => 150,
            'late_fee_grace_minutes' => 30,
            'public_booking' => '1',
            'locale' => 'bn',
            'date_format' => 'd/m/Y',
            'time_format' => 'h:i A',
            ...$overrides,
        ];
    }

    public function test_only_administrators_can_open_the_settings(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get(route('settings.edit'))->assertOk()->assertSee('Date format');

        foreach (['manager', 'staff'] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('settings.edit'))->assertForbidden();
            $this->actingAs($this->userWithRole($role))->put(route('settings.update'), $this->form())->assertForbidden();
        }

        $this->assertSame(0, Setting::count());
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get(route('settings.edit'))->assertRedirect(route('login'));
    }

    public function test_the_form_starts_from_the_configured_defaults(): void
    {
        config(['rentdesk.currency' => 'EUR', 'rentdesk.company_name' => 'Default Co']);

        $this->actingAs($this->userWithRole('admin'))->get(route('settings.edit'))
            ->assertSee('value="EUR"', false)
            ->assertSee('value="Default Co"', false)
            ->assertSee('2026-12-25')
            ->assertSee('25/12/2026')
            ->assertSee('03:30 PM');
    }

    public function test_saving_stores_every_setting(): void
    {
        $this->actingAs($this->userWithRole('admin'))
            ->put(route('settings.update'), $this->form())
            ->assertRedirect(route('settings.edit'))
            ->assertSessionHas('status');

        $stored = Setting::pluck('value', 'key')->all();

        $this->assertSame('BDT', $stored['currency']);
        $this->assertSame('Acme Rentals', $stored['company_name']);
        $this->assertSame('7.5', $stored['tax_rate']);
        $this->assertSame('1', $stored['public_booking']);
        $this->assertSame('d/m/Y', $stored['date_format']);
        $this->assertCount(10, $stored);
    }

    public function test_saved_settings_take_effect_on_the_next_request(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->put(route('settings.update'), $this->form());

        // A new request starts from the config defaults again and must pick the settings up from the database.
        config(['rentdesk.currency' => 'USD', 'rentdesk.tax_rate' => 0, 'rentdesk.public_booking' => false, 'rentdesk.date_format' => 'Y-m-d', 'app.locale' => 'en']);

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('<html lang="bn"', false);

        $this->assertSame('BDT', config('rentdesk.currency'));
        $this->assertSame(7.5, config('rentdesk.tax_rate'));
        $this->assertTrue(config('rentdesk.public_booking'));
        $this->assertSame('d/m/Y', config('rentdesk.date_format'));
        $this->assertSame(14, config('rentdesk.invoice_due_days'));
        $this->assertSame(150.0, config('rentdesk.late_fee_percent'));
    }

    public function test_the_public_booking_page_follows_the_setting(): void
    {
        config(['rentdesk.public_booking' => false]);
        $this->get(route('book.create'))->assertNotFound();

        $this->actingAs($this->userWithRole('admin'))->put(route('settings.update'), $this->form());
        config(['rentdesk.public_booking' => false]);

        $this->get(route('book.create'))->assertOk();
    }

    public function test_the_session_language_still_wins_over_the_default_language(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->put(route('settings.update'), $this->form(['locale' => 'bn']));

        $this->withSession(['locale' => 'en'])->actingAs($admin)->get(route('dashboard'))->assertSee('<html lang="en"', false);
    }

    public function test_invalid_input_is_rejected_and_nothing_is_saved(): void
    {
        $admin = $this->userWithRole('admin');

        foreach ([
            ['company_name', ''],
            ['currency', 'EURO'],
            ['currency', '12'],
            ['tax_rate', '101'],
            ['tax_rate', '-1'],
            ['invoice_due_days', '400'],
            ['late_fee_percent', 'abc'],
            ['late_fee_grace_minutes', '-5'],
            ['locale', 'xx'],
            ['date_format', 'Y-m-d; DROP'],
            ['time_format', 'g:i'],
        ] as [$field, $value]) {
            $this->actingAs($admin)->put(route('settings.update'), $this->form([$field => $value]))->assertSessionHasErrors($field);
        }

        $this->assertSame(0, Setting::count());
    }

    public function test_an_unticked_public_booking_box_turns_it_off(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->put(route('settings.update'), $this->form());
        $this->actingAs($admin)->put(route('settings.update'), $this->form(['public_booking' => '0']));

        $this->assertSame('0', Setting::find('public_booking')->value);
        $this->assertFalse(config('rentdesk.public_booking'));
    }

    public function test_only_known_settings_are_stored(): void
    {
        $this->actingAs($this->userWithRole('admin'))->put(route('settings.update'), $this->form(['app_key' => 'x', 'installed' => '0']));

        $this->assertNull(Setting::find('app_key'));
        $this->assertNull(Setting::find('installed'));
    }

    public function test_dates_follow_the_chosen_formats(): void
    {
        $moment = Carbon::parse('2026-12-25 15:30:00', 'UTC');

        $this->assertSame('2026-12-25', $moment->toAppDate());
        $this->assertSame('2026-12-25 15:30', $moment->toAppDateTime());

        config(['rentdesk.date_format' => 'd/m/Y', 'rentdesk.time_format' => 'h:i A']);

        $this->assertSame('25/12/2026', $moment->toAppDate());
        $this->assertSame('03:30 PM', $moment->toAppTime());
        $this->assertSame('25/12/2026 03:30 PM', $moment->toAppDateTime());
        $this->assertSame('25/12/2026', $moment->toImmutable()->toAppDate());
    }

    public function test_pages_and_pdfs_show_dates_in_the_chosen_format(): void
    {
        $invoice = Invoice::factory()->create(['issued_at' => '2026-12-25', 'due_at' => '2027-01-08']);
        $invoice->items()->create(['description' => 'Hammer Drill', 'qty' => 1, 'price' => 50]);
        config(['rentdesk.date_format' => 'd/m/Y']);

        $this->actingAs($this->userWithRole('admin'))->get(route('invoices.show', $invoice))->assertOk()->assertSee('25/12/2026')->assertSee('08/01/2027');

        $html = app(DocumentRenderer::class)->invoice($invoice)->getDomPDF()->outputHtml();
        $this->assertStringContainsString('25/12/2026', $html);
    }

    public function test_the_default_date_format_is_unchanged(): void
    {
        $this->assertSame('2026-12-25 15:30', Carbon::parse('2026-12-25 15:30', 'UTC')->toAppDateTime());
    }
}
