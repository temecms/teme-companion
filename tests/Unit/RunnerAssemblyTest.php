<?php

declare(strict_types=1);

namespace Teme\Companion\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Teme\Companion\Report\Runner;

final class RunnerAssemblyTest extends TestCase
{
    public function test_the_report_has_its_header_redacts_strings_and_refuses_forbidden_keys(): void
    {
        $report = Runner::assemble(
            ['content' => ['blocks' => ['blocks' => ['core/html' => ['example' => 'mail me: a@b.co from 10.1.2.3']]]]],
            ['media' => 'RuntimeException: disk'],
            ['plugin_version' => '0.1.0', 'include_examples' => true, 'generated_at' => '2026-09-22T10:00:00Z']
        );

        $this->assertSame(1, $report['report_version']);
        $this->assertSame('0.1.0', $report['plugin_version']);
        $this->assertSame('mail me: [email] from [ip]', $report['sections']['content']['blocks']['blocks']['core/html']['example']);
        $this->assertSame(['media' => 'RuntimeException: disk'], $report['errors']);

        $this->expectException(\RuntimeException::class);
        Runner::assemble(['users' => ['list' => [['user_email' => 'x']]]], [], []);
    }

    public function test_key_names_used_as_a_catalog_outside_users_and_comments_are_not_forbidden(): void
    {
        $report = Runner::assemble(
            ['meta_keys' => ['user' => ['session_tokens' => ['rows' => 5], 'nickname' => ['rows' => 12]]]],
            [],
            ['plugin_version' => '0.1.0']
        );

        $this->assertSame(1, $report['report_version']);
        $this->assertSame('0.1.0', $report['plugin_version']);
        $this->assertIsArray($report['sections']['meta_keys']);
        $this->assertSame(5, $report['sections']['meta_keys']['user']['session_tokens']['rows']);
    }

    public function test_a_forbidden_key_inside_comments_is_still_refused(): void
    {
        $this->expectException(\RuntimeException::class);
        Runner::assemble(['comments' => ['list' => [['comment_author_email' => 'x']]]], [], []);
    }
}
