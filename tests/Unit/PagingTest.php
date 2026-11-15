<?php

declare(strict_types=1);

namespace Teme\Companion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Teme\Companion\Api\Paging;

final class PagingTest extends TestCase
{
    public function test_defaults_when_nothing_is_given(): void
    {
        $this->assertSame(['after_id' => 0, 'limit' => 200, 'modified_after' => null], Paging::read([], 200, 500));
    }

    public function test_caps_the_limit_and_floors_it_at_one(): void
    {
        $this->assertSame(500, Paging::read(['limit' => '9999'], 200, 500)['limit']);
        $this->assertSame(1, Paging::read(['limit' => '-3'], 200, 500)['limit']);
    }

    public function test_reads_after_id_and_rejects_negatives(): void
    {
        $this->assertSame(41, Paging::read(['after_id' => '41'], 200, 500)['after_id']);
        $this->assertSame(0, Paging::read(['after_id' => '-5'], 200, 500)['after_id']);
    }

    public function test_accepts_only_a_well_formed_utc_timestamp_for_modified_after(): void
    {
        $this->assertSame('2026-09-01 10:00:00', Paging::read(['modified_after' => '2026-09-01 10:00:00'], 200, 500)['modified_after']);
        $this->assertSame('2026-09-01 10:00:00', Paging::read(['modified_after' => '2026-09-01T10:00:00Z'], 200, 500)['modified_after']);
        $this->assertNull(Paging::read(['modified_after' => "1' OR 1=1"], 200, 500)['modified_after']);
    }
}
