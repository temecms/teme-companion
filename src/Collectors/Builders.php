<?php
/**
 * @package Teme\Companion
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Teme\Companion\Collectors;

use Teme\Companion\Report\Collector;

/**
 * Page builders and block libraries: whether each is present and how many posts it built.
 */
final class Builders implements Collector
{
	/** @var list<array{0:string,1:string,2:string,3:?string}> name, constant, meta key, meta value */
	private const BUILDERS = [
		['elementor', 'ELEMENTOR_VERSION', '_elementor_edit_mode', 'builder'],
		['divi', 'ET_BUILDER_VERSION', '_et_pb_use_builder', 'on'],
		['wpbakery', 'WPB_VC_VERSION', '_wpb_vc_js_status', null],
		['beaver_builder', 'FL_BUILDER_VERSION', '_fl_builder_enabled', '1'],
		['oxygen', 'CT_VERSION', 'ct_builder_shortcodes', null],
		['bricks', 'BRICKS_VERSION', '_bricks_page_content_2', null],
		['siteorigin', 'SITEORIGIN_PANELS_VERSION', 'panels_data', null],
		['thrive', 'TVE_VERSION', 'tve_updated_post', null],
		['brizy', 'BRIZY_VERSION', 'brizy', null],
	];

	private const BLOCK_LIBRARIES = [
		'kadence_blocks' => 'KADENCE_BLOCKS_VERSION',
		'spectra' => 'UAGB_VER',
		'generateblocks' => 'GENERATEBLOCKS_VERSION',
		'stackable' => 'STACKABLE_VERSION',
		'gutenberg_plugin' => 'GUTENBERG_VERSION',
	];

	/**
	 * @return string
	 */
	public function key(): string
	{
		return 'builders';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function collect(): array
	{
		$builders = [];
		foreach (self::BUILDERS as [$name, $constant, $metaKey, $metaValue]) {
			$posts = Sql::postsWithMeta($metaKey, $metaValue);
			$builders[$name] = ['version' => defined($constant) ? constant($constant) : null, 'posts' => $posts];
		}

		$libraries = [];
		foreach (self::BLOCK_LIBRARIES as $name => $constant) {
			$libraries[$name] = defined($constant) ? constant($constant) : null;
		}

		return ['page_builders' => $builders, 'block_libraries' => $libraries];
	}
}
