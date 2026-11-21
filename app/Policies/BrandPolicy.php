<?php

namespace App\Policies;

class BrandPolicy extends ResourcePolicy
{
    protected function resource(): string
    {
        return 'brands';
    }
}
