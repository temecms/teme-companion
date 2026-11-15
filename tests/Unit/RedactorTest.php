<?php

declare(strict_types=1);

namespace Teme\Companion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Teme\Companion\Report\Redactor;

final class RedactorTest extends TestCase
{
    public function test_it_replaces_emails_and_ipv4_in_every_string_of_a_tree(): void
    {
        $tree = [
            'a' => 'Write to jane.doe@example.com today',
            'b' => ['c' => 'from 192.168.10.4 and 10.0.0.1'],
            'd' => 42,
            'e' => ['<a href="mailto:x@y.org">x</a>'],
        ];

        $clean = Redactor::tree($tree);

        $this->assertSame('Write to [email] today', $clean['a']);
        $this->assertSame('from [ip] and [ip]', $clean['b']['c']);
        $this->assertSame(42, $clean['d']);
        $this->assertSame('<a href="mailto:[email]">x</a>', $clean['e'][0]);
    }

    public function test_it_leaves_version_numbers_alone(): void
    {
        $this->assertSame('WordPress 6.8.1, PHP 8.3.29', Redactor::text('WordPress 6.8.1, PHP 8.3.29'));
    }

    public function test_forbidden_keys_are_reported(): void
    {
        $this->assertSame(['users.0.user_email'], Redactor::forbiddenKeys(['users' => [['user_email' => 'x', 'role' => 'editor']]]));
        $this->assertSame([], Redactor::forbiddenKeys(['blocks' => ['core/paragraph' => ['count' => 3]]]));
    }
}
