<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\Auth\Permission;
use PHPUnit\Framework\TestCase;

class PermissionGroupsTest extends TestCase
{
    public function test_every_permission_is_listed_once_under_a_heading(): void
    {
        $grouped = Permission::grouped();

        $this->assertSame(
            ['Matters', 'Clients and personal information', 'Work on the file', 'Billing', 'Users and roles', 'Firm setup', 'Audit'],
            array_keys($grouped),
        );
        $this->assertEqualsCanonicalizing(Permission::cases(), array_merge(...array_values($grouped)));
        $this->assertContains(Permission::ReversePayments, $grouped['Billing']);
        $this->assertContains(Permission::ManageTariffs, $grouped['Firm setup']);
    }
}
