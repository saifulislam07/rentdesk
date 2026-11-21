<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_manage_users(): void
    {
        $this->get(route('users.index'))->assertRedirect(route('login'));

        foreach (['manager', 'staff'] as $role) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('users.index'))->assertForbidden();
            $this->actingAs($user)
                ->post(route('users.store'), $this->validData(['role' => 'admin']))
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['email' => 'new.person@example.com']);
    }

    public function test_an_administrator_can_add_a_user_with_a_role(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)->get(route('users.index'))->assertOk()->assertSee($admin->email);
        $this->actingAs($admin)->get(route('users.create'))->assertOk();

        $this->actingAs($admin)
            ->post(route('users.store'), $this->validData())
            ->assertRedirect(route('users.index'));

        $user = User::where('email', 'new.person@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('staff'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Secret-pass-123', $user->password));
    }

    public function test_new_users_are_validated(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->post(route('users.store'), $this->validData([
                'email' => $admin->email,
                'role' => 'owner',
                'password_confirmation' => 'something else',
            ]))
            ->assertSessionHasErrors(['email', 'role', 'password']);
    }

    public function test_an_administrator_can_change_a_role_and_keep_the_password(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');
        $oldHash = $staff->password;

        $this->actingAs($admin)->get(route('users.edit', $staff))->assertOk();
        $this->actingAs($admin)
            ->put(route('users.update', $staff), [
                'name' => 'Renamed',
                'email' => $staff->email,
                'role' => 'manager',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('users.index'));

        $staff->refresh();
        $this->assertSame('Renamed', $staff->name);
        $this->assertTrue($staff->hasRole('manager'));
        $this->assertFalse($staff->hasRole('staff'));
        $this->assertSame($oldHash, $staff->password);
    }

    public function test_administrators_cannot_change_their_own_role(): void
    {
        $admin = $this->userWithRole('admin');
        $this->userWithRole('admin');

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'staff',
        ]);

        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_a_demoted_administrator_loses_access_to_users(): void
    {
        $admin = $this->userWithRole('admin');
        $other = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->put(route('users.update', $other), ['name' => $other->name, 'email' => $other->email, 'role' => 'manager'])
            ->assertRedirect(route('users.index'));

        $this->assertTrue($other->fresh()->hasRole('manager'));
        $this->actingAs($other->fresh())->get(route('users.index'))->assertForbidden();
    }

    public function test_an_administrator_can_delete_another_user_but_not_themselves(): void
    {
        $admin = $this->userWithRole('admin');
        $staff = $this->userWithRole('staff');

        $this->actingAs($admin)->delete(route('users.destroy', $staff))->assertRedirect(route('users.index'));
        $this->assertModelMissing($staff);

        $this->actingAs($admin)->delete(route('users.destroy', $admin))->assertSessionHas('error');
        $this->assertModelExists($admin);
    }

    public function test_the_only_administrator_cannot_delete_their_account_from_the_profile(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'password');

        $this->assertModelExists($admin);

        $this->userWithRole('admin');
        $this->actingAs($admin)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');
        $this->assertModelMissing($admin);
    }

    public function test_the_users_link_shows_only_for_administrators(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get(route('dashboard'))->assertSee(route('users.index'));
        $this->actingAs($this->userWithRole('manager'))->get(route('dashboard'))->assertDontSee(route('users.index'));
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Person',
            'email' => 'new.person@example.com',
            'role' => 'staff',
            'password' => 'Secret-pass-123',
            'password_confirmation' => 'Secret-pass-123',
        ], $overrides);
    }
}
