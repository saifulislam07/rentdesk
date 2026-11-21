<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Resources that expose the standard view/create/update/delete permissions.
     *
     * @var list<string>
     */
    public const RESOURCES = [
        'locations', 'categories', 'brands', 'products', 'units', 'rentals', 'customers',
        'invoices', 'maintenance', 'kits', 'transfers', 'bookings',
    ];

    /**
     * Permissions that stand alone. Reports show money, so staff do not get them.
     *
     * @var list<string>
     */
    public const SINGLE_PERMISSIONS = ['reports.view', 'settings.manage'];

    /**
     * Resources the front-desk staff role may also create and change, not just view.
     *
     * @var list<string>
     */
    private const COUNTER_RESOURCES = ['rentals', 'customers', 'invoices', 'bookings'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(self::RESOURCES)
            ->crossJoin(['view', 'create', 'update', 'delete'])
            ->map(fn (array $pair): string => implode('.', $pair))
            ->merge(self::SINGLE_PERMISSIONS);

        $permissions->each(fn (string $name) => Permission::findOrCreate($name));

        Role::findOrCreate('admin')->syncPermissions($permissions);

        Role::findOrCreate('manager')->syncPermissions(
            $permissions->reject(fn (string $name): bool => str_ends_with($name, '.delete') || $name === 'settings.manage')
        );

        Role::findOrCreate('staff')->syncPermissions($this->staffPermissions($permissions));
    }

    /**
     * @param  Collection<int, string>  $permissions
     * @return Collection<int, string>
     */
    private function staffPermissions(Collection $permissions): Collection
    {
        return $permissions
            ->reject(fn (string $name): bool => in_array($name, self::SINGLE_PERMISSIONS, true))
            ->filter(function (string $name): bool {
                [$resource, $ability] = explode('.', $name);

                return $ability === 'view'
                    || (in_array($resource, self::COUNTER_RESOURCES, true) && in_array($ability, ['create', 'update'], true));
            });
    }
}
