<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Tsubakuro_Admin helpers (comment storage, user list).
 */
class AdminTest extends TestCase {

	protected function setUp(): void {
		tsubakuro_test_reset();
	}

	// -------------------------------------------------------------------------
	// Menu / about page
	// -------------------------------------------------------------------------

	public function test_add_menu_registers_about_page(): void {
		Tsubakuro_Admin::add_menu();

		$slugs = array_column( $GLOBALS['tsubakuro_test']['submenu_pages'], 'menu_slug' );

		$this->assertContains( 'tsubakuro-about', $slugs );

		$about_index = array_search( 'tsubakuro-about', $slugs, true );
		$about_page  = $GLOBALS['tsubakuro_test']['submenu_pages'][ $about_index ];

		$this->assertSame( 'tsubakuro-tasks', $about_page['parent_slug'] );
		$this->assertSame( 'ツバクロについて', $about_page['menu_title'] );
		$this->assertSame( 'edit_posts', $about_page['capability'] );
		$this->assertSame( array( 'Tsubakuro_Admin', 'render_about' ), $about_page['callback'] );
	}

	public function test_about_page_data_is_filterable_and_contains_reference_links(): void {
		$story_items     = Tsubakuro_Admin::get_about_story_items();
		$value_points    = Tsubakuro_Admin::get_about_value_points();
		$reference_links = Tsubakuro_Admin::get_about_reference_links();

		$this->assertCount( 5, $story_items );
		$this->assertSame( '巣作り', $story_items[0]['title'] );
		$this->assertContains( 'tsubakuro_about_story_items', $GLOBALS['tsubakuro_test']['filters_applied'] );

		$this->assertNotEmpty( $value_points );
		$this->assertContains( 'tsubakuro_about_value_points', $GLOBALS['tsubakuro_test']['filters_applied'] );

		$labels = array_column( $reference_links, 'label' );
		$this->assertContains( 'タスク一覧', $labels );
		$this->assertContains( '新規タスク追加', $labels );
		$this->assertContains( 'MCP 設定', $labels );
		$this->assertContains( 'tsubakuro_about_reference_links', $GLOBALS['tsubakuro_test']['filters_applied'] );
	}

	public function test_render_about_outputs_about_content_and_reference_links(): void {
		ob_start();
		Tsubakuro_Admin::render_about();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'なぜツバクロなのか', $output );
		$this->assertStringContainsString( 'WordPress 内の課題・依頼・改善案', $output );
		$this->assertStringContainsString( '巣作り', $output );
		$this->assertStringContainsString( '軽やかに巡回しながら、課題を運び、積み上げていく存在', $output );
		$this->assertStringContainsString( 'admin.php?page=tsubakuro-tasks', $output );
		$this->assertStringContainsString( 'admin.php?page=tsubakuro-settings', $output );
	}

	// -------------------------------------------------------------------------
	// get_users_list()
	// -------------------------------------------------------------------------

	public function test_get_users_list_returns_empty_array_when_no_users(): void {
		$list = Tsubakuro_Admin::get_users_list();

		$this->assertSame( array(), $list );
	}

	public function test_get_users_list_returns_formatted_user_entries(): void {
		$GLOBALS['tsubakuro_test']['users'][3]  = (object) array( 'ID' => 3, 'display_name' => 'Alice' );
		$GLOBALS['tsubakuro_test']['users'][7]  = (object) array( 'ID' => 7, 'display_name' => 'Bob' );

		$list = Tsubakuro_Admin::get_users_list();

		$this->assertCount( 2, $list );

		$ids = array_column( $list, 'id' );
		$this->assertContains( 3, $ids );
		$this->assertContains( 7, $ids );

		$names = array_column( $list, 'name' );
		$this->assertContains( 'Alice', $names );
		$this->assertContains( 'Bob', $names );
	}

	// -------------------------------------------------------------------------
	// insert_comment()
	// -------------------------------------------------------------------------

	public function test_insert_comment_returns_new_comment_id(): void {
		$id = Tsubakuro_Admin::insert_comment( 101, 7, 'Hello world' );

		$this->assertSame( 1, $id );
	}

	public function test_insert_comment_stores_data_in_mock_table(): void {
		Tsubakuro_Admin::insert_comment( 101, 7, 'My comment' );

		$row = $GLOBALS['tsubakuro_test']['comments'][1];
		$this->assertSame( 101, $row['task_id'] );
		$this->assertSame( 7, $row['user_id'] );
		$this->assertSame( 'My comment', $row['comment'] );
	}

	public function test_insert_comment_returns_false_on_db_failure(): void {
		$GLOBALS['tsubakuro_test']['wpdb_insert_fail'] = true;

		$result = Tsubakuro_Admin::insert_comment( 101, 7, 'Hello' );

		$this->assertFalse( $result );
	}

	public function test_insert_comment_increments_id_for_each_row(): void {
		$first  = Tsubakuro_Admin::insert_comment( 101, 7, 'First' );
		$second = Tsubakuro_Admin::insert_comment( 101, 7, 'Second' );

		$this->assertSame( 1, $first );
		$this->assertSame( 2, $second );
	}

	// -------------------------------------------------------------------------
	// get_comment()
	// -------------------------------------------------------------------------

	public function test_get_comment_returns_null_when_row_not_found(): void {
		// wpdb_row is null by default.
		$result = Tsubakuro_Admin::get_comment( 99 );

		$this->assertNull( $result );
	}

	public function test_get_comment_returns_formatted_comment_with_known_user(): void {
		$GLOBALS['tsubakuro_test']['wpdb_row'] = array(
			'id'         => 5,
			'task_id'    => 101,
			'user_id'    => 7,
			'comment'    => 'Great progress',
			'created_at' => '2026-05-01 12:00:00',
		);
		$GLOBALS['tsubakuro_test']['users'][7] = (object) array(
			'ID'           => 7,
			'display_name' => 'Carol',
		);

		$result = Tsubakuro_Admin::get_comment( 5 );

		$this->assertSame( 5, $result['id'] );
		$this->assertSame( 101, $result['task_id'] );
		$this->assertSame( 7, $result['user_id'] );
		$this->assertSame( 'Carol', $result['user_name'] );
		$this->assertSame( 'Great progress', $result['comment'] );
	}

	public function test_get_comment_uses_fallback_username_when_user_not_found(): void {
		$GLOBALS['tsubakuro_test']['wpdb_row'] = array(
			'id'         => 5,
			'task_id'    => 101,
			'user_id'    => 99,
			'comment'    => 'Hi',
			'created_at' => '2026-05-01 12:00:00',
		);
		// User 99 not in store.

		$result = Tsubakuro_Admin::get_comment( 5 );

		$this->assertSame( '不明', $result['user_name'] );
	}

	// -------------------------------------------------------------------------
	// get_task_comments()
	// -------------------------------------------------------------------------

	public function test_get_task_comments_returns_empty_array_when_no_rows(): void {
		$result = Tsubakuro_Admin::get_task_comments( 101 );

		$this->assertSame( array(), $result );
	}

	public function test_get_task_comments_returns_formatted_comment_list(): void {
		$GLOBALS['tsubakuro_test']['wpdb_rows'] = array(
			array(
				'id'         => 1,
				'task_id'    => 101,
				'user_id'    => 7,
				'comment'    => 'First',
				'created_at' => '2026-05-01 09:00:00',
			),
			array(
				'id'         => 2,
				'task_id'    => 101,
				'user_id'    => 0,
				'comment'    => 'Second',
				'created_at' => '2026-05-01 10:00:00',
			),
		);
		$GLOBALS['tsubakuro_test']['users'][7] = (object) array(
			'ID'           => 7,
			'display_name' => 'Dave',
		);

		$result = Tsubakuro_Admin::get_task_comments( 101 );

		$this->assertCount( 2, $result );
		$this->assertSame( 1, $result[0]['id'] );
		$this->assertSame( 'Dave', $result[0]['user_name'] );
		$this->assertSame( '不明', $result[1]['user_name'] );
		$this->assertSame( 'Second', $result[1]['comment'] );
	}
}
