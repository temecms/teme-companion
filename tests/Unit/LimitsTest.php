<?php

declare(strict_types=1);

namespace Teme\Companion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Teme\Companion\Report\Limits;

final class LimitsTest extends TestCase
{
    public function test_bump_counts_and_folds_new_keys_into_other_past_the_cap(): void
    {
        $counts = [];
        Limits::bump($counts, 'a', 2);
        Limits::bump($counts, 'b', 2);
        Limits::bump($counts, 'c', 2);
        Limits::bump($counts, 'a', 2);

        $this->assertSame(['a' => 2, 'b' => 1, '(other)' => 1], $counts);
    }

    public function test_cut_shortens_to_the_limit_with_an_ellipsis(): void
    {
        $this->assertSame('abc…', Limits::cut('abcdef', 3));
        $this->assertSame('abc', Limits::cut('abc', 3));
    }

    public function test_top_sorts_descending_and_keeps_n(): void
    {
        $this->assertSame(['b' => 5, 'c' => 3], Limits::top(['a' => 1, 'b' => 5, 'c' => 3], 2));
    }
}
