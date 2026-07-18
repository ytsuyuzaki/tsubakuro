<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Tsubakuro_Site_Strategy_Admin menu registration and rendering.
 */
class SiteStrategyAdminTest extends TestCase
{

	protected function setUp(): void
	{
		tsubakuro_test_reset();
		$_GET  = array();
		$_POST = array();
		$GLOBALS['tsubakuro_test']['can']['edit_posts'] = true;
	}

	public function test_add_menu_registers_site_strategy_page(): void
	{
		Tsubakuro_Site_Strategy_Admin::add_menu();

		$slugs = array_column($GLOBALS['tsubakuro_test']['submenu_pages'], 'menu_slug');
		$this->assertContains('tsubakuro-site-strategy', $slugs);

		$index = array_search('tsubakuro-site-strategy', $slugs, true);
		$page  = $GLOBALS['tsubakuro_test']['submenu_pages'][$index];

		$this->assertSame('tsubakuro-tasks', $page['parent_slug']);
		$this->assertSame('サイト方針', $page['menu_title']);
		$this->assertSame('edit_posts', $page['capability']);
		$this->assertSame(array('Tsubakuro_Site_Strategy_Admin', 'render_page'), $page['callback']);
	}

	public function test_render_page_outputs_form_with_saved_values(): void
	{
		Tsubakuro_Site_Strategy::save_strategy(array(
			'purpose'   => '目的テキスト',
			'direction' => '方向性テキスト',
		));

		ob_start();
		Tsubakuro_Site_Strategy_Admin::render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString('サイト方針', $output);
		$this->assertStringContainsString('目的テキスト', $output);
		$this->assertStringContainsString('方向性テキスト', $output);
		$this->assertStringContainsString('tsubakuro_save_site_strategy', $output);
		$this->assertStringContainsString('最終更新', $output);
	}

	public function test_render_page_shows_saved_notice(): void
	{
		$_GET['message'] = 'saved';

		ob_start();
		Tsubakuro_Site_Strategy_Admin::render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString('サイト方針を保存しました。', $output);
	}
}
