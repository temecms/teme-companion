<?php

declare(strict_types=1);

namespace Teme\Companion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Teme\Companion\Support\Safe;

final class SafeTest extends TestCase
{
    public function test_it_unserializes_arrays(): void
    {
        $this->assertSame(['a' => 1, 'b' => ['x']], Safe::unserialize(serialize(['a' => 1, 'b' => ['x']])));
    }

    public function test_it_never_creates_objects(): void
    {
        $value = Safe::unserialize(serialize(['o' => new \ArrayObject([1])]));

        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $value['o']);
    }

    public function test_plain_and_broken_strings_come_back_unchanged(): void
    {
        $this->assertSame('hello', Safe::unserialize('hello'));
        $this->assertSame('a:1:{broken', Safe::unserialize('a:1:{broken'));
    }
}
