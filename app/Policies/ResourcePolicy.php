<?php

namespace App\Policies;

use App\Models\User;

/**
 * Maps the standard CRUD abilities to "<resource>.<ability>" permissions.
 */
abstract class ResourcePolicy
{
    /**
     * The permission prefix, e.g. "products".
     */
    abstract protected function resource(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->resource().'.view');
    }

    public function view(User $user): bool
    {
        return $user->can($this->resource().'.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->resource().'.create');
    }

    public function update(User $user): bool
    {
        return $user->can($this->resource().'.update');
    }

    public function delete(User $user): bool
    {
        return $user->can($this->resource().'.delete');
    }
}
