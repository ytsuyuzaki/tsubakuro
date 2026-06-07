<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Tsubakuro_Frontend hook registration and asset enqueueing.
 */
class FrontendTest extends TestCase {

	protected function setUp(): void {
		tsubakuro_test_reset();
	}

	// -------------------------------------------------------------------------
	// init()
	// -------------------------------------------------------------------------

	public function test_init_registers_frontend_hooks_outside_admin(): void {
		// is_admin defaults to false.
		Tsubakuro_Frontend::init();

		$actions = $GLOBALS['tsubakuro_test']['actions'];
		$this->assertArrayHasKey( 'wp_enqueue_scripts', $actions );
		$this->assertArrayHasKey( 'wp_footer', $actions );
		$this->assertArrayHasKey( 'admin_bar_menu', $actions );
	}

	public function test_init_skips_hooks_when_is_admin(): void {
		$GLOBALS['tsubakuro_test']['is_admin'] = true;

		Tsubakuro_Frontend::init();

		$actions = $GLOBALS['tsubakuro_test']['actions'];
		$this->assertArrayNotHasKey( 'wp_enqueue_scripts', $actions );
		$this->assertArrayNotHasKey( 'wp_footer', $actions );
		$this->assertArrayNotHasKey( 'admin_bar_menu', $actions );
	}

	// -------------------------------------------------------------------------
	// enqueue_scripts() / render_popup() — should_show() gating
	// -------------------------------------------------------------------------

	public function test_enqueue_scripts_enqueues_assets_when_user_can_edit(): void {
		$GLOBALS['tsubakuro_test']['is_logged_in'] = true;
		// 'edit_posts' is already true in defaults.

		Tsubakuro_Frontend::enqueue_scripts();

		$this->assertContains( 'tsubakuro-public', $GLOBALS['tsubakuro_test']['enqueued_styles'] );
		$this->assertContains( 'tsubakuro-public', $GLOBALS['tsubakuro_test']['enqueued_scripts'] );
	}

	public function test_enqueue_scripts_skips_assets_when_not_logged_in(): void {
		$GLOBALS['tsubakuro_test']['is_logged_in'] = false;

		Tsubakuro_Frontend::enqueue_scripts();

		$this->assertNotContains( 'tsubakuro-public', $GLOBALS['tsubakuro_test']['enqueued_styles'] );
		$this->assertNotContains( 'tsubakuro-public', $GLOBALS['tsubakuro_test']['enqueued_scripts'] );
	}

	public function test_enqueue_scripts_skips_assets_when_user_cannot_edit(): void {
		$GLOBALS['tsubakuro_test']['is_logged_in']       = true;
		$GLOBALS['tsubakuro_test']['can']['edit_posts']  = false;

		Tsubakuro_Frontend::enqueue_scripts();

		$this->assertNotContains( 'tsubakuro-public', $GLOBALS['tsubakuro_test']['enqueued_scripts'] );
	}

	// -------------------------------------------------------------------------
	// add_admin_bar_button()
	// -------------------------------------------------------------------------

	public function test_add_admin_bar_button_skips_when_should_not_show(): void {
		$GLOBALS['tsubakuro_test']['is_logged_in'] = false;

		// WP_Admin_Bar stub – just needs to accept add_node() without error.
		$bar = new class() {
			public $nodes = array();
			public function add_node( $args ) { $this->nodes[] = $args; }
		};

		Tsubakuro_Frontend::add_admin_bar_button( $bar );

		$this->assertCount( 0, $bar->nodes );
	}

	public function test_add_admin_bar_button_adds_node_when_user_can_edit(): void {
		$GLOBALS['tsubakuro_test']['is_logged_in'] = true;
		// manage_options is true by default, so 4 nodes are added (including page-tasks).

		$bar = new class() {
			public $nodes = array();
			public function add_node( $args ) { $this->nodes[] = $args; }
		};

		Tsubakuro_Frontend::add_admin_bar_button( $bar );

		$ids = array_column( $bar->nodes, 'id' );
		$this->assertContains( 'tsubakuro-panel-toggle', $ids );
		$this->assertContains( 'tsubakuro-list', $ids );
		$this->assertContains( 'tsubakuro-new', $ids );
	}

	public function test_add_admin_bar_button_adds_page_tasks_node_for_manage_options_with_page(): void {
		$GLOBALS['tsubakuro_test']['is_logged_in']         = true;
		// manage_options is true by default.

		$bar = new class() {
			public $nodes = array();
			public function add_node( $args ) { $this->nodes[] = $args; }
		};

		// get_queried_object_id() returns 55 in test stubs.
		Tsubakuro_Frontend::add_admin_bar_button( $bar );

		$ids = array_column( $bar->nodes, 'id' );
		$this->assertContains( 'tsubakuro-page-tasks', $ids );

		$page_tasks_node = $bar->nodes[ array_search( 'tsubakuro-page-tasks', $ids, true ) ];
		$this->assertStringContainsString( 'related_page=55', $page_tasks_node['href'] );
	}

	public function test_add_admin_bar_button_does_not_add_page_tasks_node_without_manage_options(): void {
		$GLOBALS['tsubakuro_test']['is_logged_in']           = true;
		$GLOBALS['tsubakuro_test']['can']['manage_options']  = false;

		$bar = new class() {
			public $nodes = array();
			public function add_node( $args ) { $this->nodes[] = $args; }
		};

		Tsubakuro_Frontend::add_admin_bar_button( $bar );

		$ids = array_column( $bar->nodes, 'id' );
		$this->assertNotContains( 'tsubakuro-page-tasks', $ids );
		$this->assertCount( 3, $bar->nodes );
	}
}
