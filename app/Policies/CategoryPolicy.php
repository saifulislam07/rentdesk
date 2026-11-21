<?php

namespace App\Policies;

class CategoryPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'categories';
    }
}
