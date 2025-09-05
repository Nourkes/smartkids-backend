<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\ParentModel;
use App\Policies\ParentPolicy;

class AuthServiceProvider extends ServiceProvider
{
protected $policies = [
    ParentModel::class => ParentPolicy::class,
];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
