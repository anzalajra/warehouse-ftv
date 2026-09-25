<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('roles')) {
            foreach (['super_admin', 'admin', 'staff'] as $role) {
                Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            }
        }
    }
}
