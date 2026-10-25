<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Clients;

use App\Enums\Client\ContactKind;
use App\Services\Clients\MaskIdentifier;
use PHPUnit\Framework\TestCase;

class MaskIdentifierTest extends TestCase
{
    public function test_a_contact_is_masked_the_way_its_kind_is(): void
    {
        $this->assertSame('082 ··· 4567', MaskIdentifier::contact(ContactKind::Mobile, '0821234567'));
        $this->assertSame('le····@example.com', MaskIdentifier::contact(ContactKind::Email, 'lerato@example.com'));
        $this->assertSame(MaskIdentifier::address('1142 Boitekong Ext 4'), MaskIdentifier::contact(ContactKind::Physical, '1142 Boitekong Ext 4'));
        $this->assertSame(MaskIdentifier::address('PO Box 12'), MaskIdentifier::contact(ContactKind::Postal, 'PO Box 12'));
    }
}
