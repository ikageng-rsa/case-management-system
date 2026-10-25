<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Matter;

use App\Http\Requests\Matter\ListMattersRequest;
use Tests\TestCase;

class ListMattersRequestTest extends TestCase
{
    public function test_it_reads_a_known_filter_sort_and_type(): void
    {
        $request = ListMattersRequest::create('/matters', 'GET', ['filter' => 'closed', 'sort' => 'reference', 'type' => '4']);

        $this->assertSame('closed', $request->filter());
        $this->assertSame('reference', $request->sort());
        $this->assertSame(4, $request->matterTypeId());
    }

    public function test_it_falls_back_to_the_default_view_for_anything_it_does_not_recognise(): void
    {
        $request = ListMattersRequest::create('/matters', 'GET', ['filter' => 'deleted', 'sort' => ['x'], 'type' => 'abc']);

        $this->assertSame('all', $request->filter());
        $this->assertSame('next_date', $request->sort());
        $this->assertNull($request->matterTypeId());
    }
}
