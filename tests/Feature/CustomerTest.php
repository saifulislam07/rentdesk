<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\Rental;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return ['type' => 'person', 'name' => 'Jane Doe', 'email' => 'jane@example.test', ...$overrides];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('customers.index'))->assertRedirect(route('login'));
    }

    public function test_staff_can_create_and_update_customers(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->post(route('customers.store'), $this->payload())->assertRedirect();

        $customer = Customer::firstWhere('email', 'jane@example.test');
        $this->assertNotNull($customer);

        $this->actingAs($staff)
            ->put(route('customers.update', $customer), $this->payload(['name' => 'Jane Smith', 'is_blacklisted' => '1']))
            ->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Jane Smith', 'is_blacklisted' => true]);
    }

    public function test_customer_data_is_validated(): void
    {
        $this->actingAs($this->userWithRole('staff'))
            ->post(route('customers.store'), $this->payload(['name' => '', 'email' => 'nope', 'type' => 'robot']))
            ->assertSessionHasErrors(['name', 'email', 'type']);
    }

    public function test_only_admins_can_delete_customers(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($this->userWithRole('staff'))->delete(route('customers.destroy', $customer))->assertForbidden();
        $this->actingAs($this->userWithRole('manager'))->delete(route('customers.destroy', $customer))->assertForbidden();
        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertModelMissing($customer);
    }

    public function test_a_customer_with_rentals_cannot_be_deleted(): void
    {
        $rental = Rental::factory()->create();

        $this->actingAs($this->userWithRole('admin'))
            ->delete(route('customers.destroy', $rental->customer))
            ->assertSessionHas('error');

        $this->assertModelExists($rental->customer);
    }

    public function test_customers_can_be_searched(): void
    {
        Customer::factory()->create(['name' => 'Alice Archer']);
        Customer::factory()->create(['name' => 'Bob Builder']);

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('customers.index', ['q' => 'alice']))
            ->assertOk()
            ->assertSee('Alice Archer')
            ->assertDontSee('Bob Builder');
    }

    public function test_the_profile_shows_rental_history(): void
    {
        $rental = Rental::factory()->create();

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('customers.show', $rental->customer))
            ->assertOk()
            ->assertSee($rental->number);
    }

    public function test_blacklisted_customers_are_not_offered_on_the_quote_form(): void
    {
        Customer::factory()->create(['name' => 'Good Customer']);
        Customer::factory()->blacklisted()->create(['name' => 'Bad Customer']);

        $this->actingAs($this->userWithRole('staff'))
            ->get(route('rentals.create'))
            ->assertSee('Good Customer')
            ->assertDontSee('Bad Customer');
    }

    public function test_staff_can_upload_a_document_to_private_storage(): void
    {
        Storage::fake('local');
        $customer = Customer::factory()->create();

        $this->actingAs($this->userWithRole('staff'))
            ->post(route('customers.documents.store', $customer), [
                'type' => 'passport',
                'file' => UploadedFile::fake()->image('passport.png'),
            ])
            ->assertSessionHas('status');

        $document = $customer->documents()->firstOrFail();

        Storage::disk('local')->assertExists($document->file_path);
        $this->assertSame('passport.png', $document->original_name);
        $this->assertFalse($document->isVerified());
    }

    public function test_documents_must_be_images_or_pdfs_under_the_size_limit(): void
    {
        Storage::fake('local');
        $customer = Customer::factory()->create();
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)
            ->post(route('customers.documents.store', $customer), [
                'type' => 'passport',
                'file' => UploadedFile::fake()->create('malware.exe', 10),
            ])
            ->assertSessionHasErrors('file');

        $this->actingAs($staff)
            ->post(route('customers.documents.store', $customer), [
                'type' => 'passport',
                'file' => UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_documents_download_through_an_authorised_route_only(): void
    {
        Storage::fake('local');
        $document = CustomerDocument::factory()->create(['file_path' => 'customer-documents/1/id.pdf']);
        Storage::disk('local')->put($document->file_path, 'secret');

        $this->get(route('documents.show', $document))->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole('staff'))
            ->get(route('documents.show', $document))
            ->assertOk()
            ->assertDownload('id-card.pdf');
    }

    public function test_a_document_can_be_verified_and_unverified(): void
    {
        $document = CustomerDocument::factory()->create();
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->patch(route('documents.update', $document));
        $this->assertTrue($document->refresh()->isVerified());

        $this->actingAs($staff)->patch(route('documents.update', $document));
        $this->assertFalse($document->refresh()->isVerified());
    }

    public function test_deleting_a_document_removes_its_file(): void
    {
        Storage::fake('local');
        $document = CustomerDocument::factory()->create(['file_path' => 'customer-documents/1/id.pdf']);
        Storage::disk('local')->put($document->file_path, 'secret');

        $this->actingAs($this->userWithRole('staff'))->delete(route('documents.destroy', $document));

        $this->assertModelMissing($document);
        Storage::disk('local')->assertMissing('customer-documents/1/id.pdf');
    }

    public function test_viewers_cannot_manage_documents(): void
    {
        $this->seed(RoleSeeder::class);
        $viewer = User::factory()->create()->givePermissionTo('customers.view');
        $document = CustomerDocument::factory()->create();

        $this->actingAs($viewer)->patch(route('documents.update', $document))->assertForbidden();
        $this->actingAs($viewer)->delete(route('documents.destroy', $document))->assertForbidden();
        $this->actingAs($viewer)
            ->post(route('customers.documents.store', $document->customer), [
                'type' => 'passport',
                'file' => UploadedFile::fake()->image('p.png'),
            ])
            ->assertForbidden();
    }

    public function test_every_customer_page_renders(): void
    {
        $customer = Customer::factory()->company()->create();
        CustomerDocument::factory()->for($customer)->verified()->create();
        $manager = $this->userWithRole('manager');

        foreach ([route('customers.index'), route('customers.create'), route('customers.edit', $customer), route('customers.show', $customer)] as $page) {
            $this->actingAs($manager)->get($page)->assertOk();
        }
    }
}
