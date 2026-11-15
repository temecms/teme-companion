<?php

declare(strict_types=1);

namespace Teme\Companion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Teme\Companion\Api\Envelope;

final class EnvelopeTest extends TestCase
{
    public function test_a_full_page_points_to_the_next_one(): void
    {
        $page = Envelope::make([['id' => 3], ['id' => 7]], 2, static fn (array $i): int => $i['id']);

        $this->assertSame(['api' => 1, 'items' => [['id' => 3], ['id' => 7]], 'next' => ['after_id' => 7], 'limit' => 2], $page);
    }

    public function test_a_short_page_is_the_last(): void
    {
        $this->assertNull(Envelope::make([['id' => 3]], 2, static fn (array $i): int => $i['id'])['next']);
        $this->assertNull(Envelope::make([], 2, static fn (array $i): int => $i['id'])['next']);
    }
}
