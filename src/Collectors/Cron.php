<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Collectors;

use Teme\Companion\Report\Collector;
use Teme\Companion\Report\Limits;

/**
 * Scheduled tasks by hook name and schedule: the plugins that do background work.
 */
final class Cron implements Collector
{
	/**
	 * @return string
	 */
	public function key(): string
	{
		return 'cron';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function collect(): array
	{
		$hooks = [];
		$schedules = [];
		foreach ((array) _get_cron_array() as $events) {
			foreach ((array) $events as $hook => $instances) {
				foreach ((array) $instances as $instance) {
					Limits::bump($hooks, (string) $hook, 300);
					Limits::bump($schedules, (string) ($instance['schedule'] ?? 'once'), 50);
				}
			}
		}

		return ['hooks' => Limits::top($hooks, 300), 'schedules' => $schedules];
	}
}
