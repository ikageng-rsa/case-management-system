<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Audit;

use App\Http\Requests\Audit\FilterAuditLogRequest;
use Tests\TestCase;

class FilterAuditLogRequestTest extends TestCase
{
    public function test_it_reads_the_actor_and_the_action(): void
    {
        $request = FilterAuditLogRequest::create('/audit-log', 'GET', ['actor' => 'a1b2', 'action' => 'updated']);

        $this->assertSame('a1b2', $request->actor());
        $this->assertSame('updated', $request->action());
    }

    public function test_an_empty_or_malformed_filter_shows_the_whole_log(): void
    {
        $request = FilterAuditLogRequest::create('/audit-log', 'GET', ['actor' => '', 'action' => ['updated']]);

        $this->assertNull($request->actor());
        $this->assertNull($request->action());
    }
}
