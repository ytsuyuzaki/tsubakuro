<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Tsubakuro_Admin helpers (comment storage, user list).
 */
class AdminTest extends TestCase
{

	protected function setUp(): void
	{
		tsubakuro_test_reset();
		$_GET  = array();
		$_POST = array();
	}

	// -------------------------------------------------------------------------
	// Menu / about page
	// -------------------------------------------------------------------------

	public function test_add_menu_registers_about_page(): void
	{
		Tsubakuro_Admin::add_menu();

		$slugs = array_column($GLOBALS['tsubakuro_test']['submenu_pages'], 'menu_slug');

		$this->assertContains('tsubakuro-about', $slugs);

		$about_index = array_search('tsubakuro-about', $slugs, true);
		$about_page  = $GLOBALS['tsubakuro_test']['submenu_pages'][$about_index];

		$this->assertSame('tsubakuro-tasks', $about_page['parent_slug']);
		$this->assertSame('ツバクロについて', $about_page['menu_title']);
		$this->assertSame('edit_posts', $about_page['capability']);
		$this->assertSame(array('Tsubakuro_Admin', 'render_about'), $about_page['callback']);
	}

	public function test_about_page_data_is_filterable_and_contains_reference_links(): void
	{
		$story_items     = Tsubakuro_Admin::get_about_story_items();
		$value_points    = Tsubakuro_Admin::get_about_value_points();
		$reference_links = Tsubakuro_Admin::get_about_reference_links();

		$this->assertCount(5, $story_items);
		$this->assertSame('巣作り', $story_items[0]['title']);
		$this->assertContains('tsubakuro_about_story_items', $GLOBALS['tsubakuro_test']['filters_applied']);

		$this->assertNotEmpty($value_points);
		$this->assertContains('tsubakuro_about_value_points', $GLOBALS['tsubakuro_test']['filters_applied']);

		$labels = array_column($reference_links, 'label');
		$this->assertContains('タスク一覧', $labels);
		$this->assertContains('新規タスク追加', $labels);
		$this->assertContains('タスク管理について', $labels);
		$this->assertContains('tsubakuro_about_reference_links', $GLOBALS['tsubakuro_test']['filters_applied']);
	}

	public function test_render_about_outputs_about_content_and_reference_links(): void
	{
		ob_start();
		Tsubakuro_Admin::render_about();
		$output = ob_get_clean();

		$this->assertStringContainsString('なぜツバクロなのか', $output);
		$this->assertStringContainsString('WordPress 内の課題・依頼・改善案', $output);
		$this->assertStringContainsString('巣作り', $output);
		$this->assertStringContainsString('軽やかに巡回しながら、課題を運び、積み上げていく存在', $output);
		$this->assertStringContainsString('admin.php?page=tsubakuro-tasks', $output);
		$this->assertStringContainsString('admin.php?page=tsubakuro-about', $output);
	}

	// -------------------------------------------------------------------------
	// Task list page
	// -------------------------------------------------------------------------

	private function make_task_post(int $id, string $title, string $content = 'Body'): object
	{
		return (object) array(
			'ID'            => $id,
			'post_type'     => 'tsubakuro_task',
			'post_title'    => $title,
			'post_content'  => $content,
			'post_date'     => '2026-05-01 10:00:00',
			'post_modified' => '2026-05-01 11:00:00',
			'post_author'   => 1,
		);
	}

	public function test_task_list_args_from_request_sanitizes_filters_search_and_sort(): void
	{
		$_GET = array(
			'status'   => 'in_progress',
			'assignee' => '7',
			's'        => '<b>urgent</b>',
			'orderby'  => 'title',
			'order'    => 'ASC',
		);

		$args = Tsubakuro_Admin::get_task_list_args_from_request();

		$this->assertSame('in_progress', $args['status']);
		$this->assertSame(7, $args['assignee']);
		$this->assertSame('urgent', $args['s']);
		$this->assertSame('title', $args['orderby']);
		$this->assertSame('ASC', $args['order']);
	}

	public function test_task_list_args_from_request_drops_invalid_filters_and_sort(): void
	{
		$_GET = array(
			'status'  => 'unknown',
			'orderby' => 'bad_column',
			'order'   => 'SIDEWAYS',
		);

		$args = Tsubakuro_Admin::get_task_list_args_from_request();

		$this->assertSame('todo', $args['status']);
		$this->assertSame('date', $args['orderby']);
		$this->assertSame('DESC', $args['order']);
	}

	public function test_task_list_args_from_request_defaults_to_todo_when_no_status_param(): void
	{
		$_GET = array();

		$args = Tsubakuro_Admin::get_task_list_args_from_request();

		$this->assertSame('todo', $args['status']);
	}

	public function test_task_list_args_from_request_accepts_all_as_show_all(): void
	{
		$_GET = array('status' => 'all');

		$args = Tsubakuro_Admin::get_task_list_args_from_request();

		$this->assertSame('all', $args['status']);
	}

	public function test_render_task_list_outputs_wp_style_controls_and_task_rows(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_task_post(101, 'Urgent task', 'Needs work');
		$GLOBALS['tsubakuro_test']['post_meta'][101] = array(
			'_tsubakuro_status'   => array('in_progress'),
			'_tsubakuro_assignee' => array(7),
		);
		$GLOBALS['tsubakuro_test']['users'][7] = (object) array(
			'ID'           => 7,
			'display_name' => 'Editor User',
		);
		$_GET = array(
			'status'   => 'in_progress',
			'assignee' => '7',
			's'        => 'Urgent',
			'orderby'  => 'title',
			'order'    => 'ASC',
		);

		ob_start();
		Tsubakuro_Admin::render_task_list();
		$output = ob_get_clean();

		$this->assertStringContainsString('name="task_ids[]"', $output);
		$this->assertStringContainsString('tsubakuro_bulk_tasks', $output);
		$this->assertStringContainsString('id="tsubakuro-task-search-input"', $output);
		$this->assertStringContainsString('id="tsubakuro-filter-status"', $output);
		$this->assertStringContainsString('id="tsubakuro-filter-assignee"', $output);
		$this->assertStringContainsString('orderby=title', $output);
		$this->assertStringContainsString('Urgent task', $output);
		$this->assertStringContainsString('Editor User', $output);
	}

	public function test_delete_tasks_sanitizes_ids_and_returns_deleted_count(): void
	{
		$deleted = Tsubakuro_Admin::delete_tasks(array('10', '10', '0', '-3', 'abc'));

		$this->assertSame(2, $deleted);
		$this->assertSame(array(10, 3), $GLOBALS['tsubakuro_test']['deleted_posts']);
	}

	// -------------------------------------------------------------------------
	// get_users_list()
	// -------------------------------------------------------------------------

	public function test_get_users_list_returns_empty_array_when_no_users(): void
	{
		$list = Tsubakuro_Admin::get_users_list();

		$this->assertSame(array(), $list);
	}

	public function test_get_users_list_returns_formatted_user_entries(): void
	{
		$GLOBALS['tsubakuro_test']['users'][3]  = (object) array('ID' => 3, 'display_name' => 'Alice');
		$GLOBALS['tsubakuro_test']['users'][7]  = (object) array('ID' => 7, 'display_name' => 'Bob');

		$list = Tsubakuro_Admin::get_users_list();

		$this->assertCount(2, $list);

		$ids = array_column($list, 'id');
		$this->assertContains(3, $ids);
		$this->assertContains(7, $ids);

		$names = array_column($list, 'name');
		$this->assertContains('Alice', $names);
		$this->assertContains('Bob', $names);
	}

	public function test_get_users_list_queries_editors_with_capability_argument(): void
	{
		Tsubakuro_Admin::get_users_list();

		$args = $GLOBALS['tsubakuro_test']['last_get_users_args'];
		$this->assertSame('edit_posts', $args['capability']);
		$this->assertArrayNotHasKey('who', $args);
	}

	// -------------------------------------------------------------------------
	// insert_comment()
	// -------------------------------------------------------------------------

	public function test_insert_comment_returns_new_comment_id(): void
	{
		$id = Tsubakuro_Admin::insert_comment(101, 7, 'Hello world');

		$this->assertIsInt($id);
		$this->assertGreaterThan(0, $id);
	}

	public function test_insert_comment_stores_data_as_task_comment_post(): void
	{
		$id = Tsubakuro_Admin::insert_comment(101, 7, 'My comment');

		$row = $GLOBALS['tsubakuro_test']['posts'][$id];
		$this->assertSame(Tsubakuro_Post_Types::COMMENT_POST_TYPE, $row->post_type);
		$this->assertSame(101, $row->post_parent);
		$this->assertSame(7, $row->post_author);
		$this->assertSame('My comment', $row->post_content);
	}

	public function test_insert_comment_returns_false_on_db_failure(): void
	{
		$GLOBALS['tsubakuro_test']['wpdb_insert_fail'] = true;

		$result = Tsubakuro_Admin::insert_comment(101, 7, 'Hello');

		$this->assertFalse($result);
	}

	public function test_insert_comment_increments_id_for_each_row(): void
	{
		$first  = Tsubakuro_Admin::insert_comment(101, 7, 'First');
		$second = Tsubakuro_Admin::insert_comment(101, 7, 'Second');

		$this->assertNotSame($first, $second);
		$this->assertGreaterThan($first, $second);
	}

	// -------------------------------------------------------------------------
	// get_comment()
	// -------------------------------------------------------------------------

	public function test_get_comment_returns_null_when_row_not_found(): void
	{
		$result = Tsubakuro_Admin::get_comment(99);

		$this->assertNull($result);
	}

	public function test_get_comment_returns_formatted_comment_with_known_user(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][5] = (object) array(
			'ID'            => 5,
			'post_type'     => Tsubakuro_Post_Types::COMMENT_POST_TYPE,
			'post_author'   => 7,
			'post_parent'   => 101,
			'post_content'  => 'Great progress',
			'post_date'     => '2026-05-01 12:00:00',
			'post_modified' => '2026-05-01 12:00:00',
		);
		$GLOBALS['tsubakuro_test']['users'][7] = (object) array(
			'ID'           => 7,
			'display_name' => 'Carol',
		);

		$result = Tsubakuro_Admin::get_comment(5);

		$this->assertSame(5, $result['id']);
		$this->assertSame(101, $result['task_id']);
		$this->assertSame(7, $result['user_id']);
		$this->assertSame('Carol', $result['user_name']);
		$this->assertSame('Great progress', $result['comment']);
	}

	public function test_get_comment_returns_null_for_non_tsubakuro_comment(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][5] = (object) array(
			'ID'            => 5,
			'post_type'     => 'post',
			'post_author'   => 7,
			'post_parent'   => 101,
			'post_content'  => 'Regular comment',
			'post_date'     => '2026-05-01 12:00:00',
			'post_modified' => '2026-05-01 12:00:00',
		);

		$result = Tsubakuro_Admin::get_comment(5);

		$this->assertNull($result);
	}

	public function test_get_comment_uses_fallback_username_when_user_not_found(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][5] = (object) array(
			'ID'            => 5,
			'post_type'     => Tsubakuro_Post_Types::COMMENT_POST_TYPE,
			'post_author'   => 99,
			'post_parent'   => 101,
			'post_content'  => 'Hi',
			'post_date'     => '2026-05-01 12:00:00',
			'post_modified' => '2026-05-01 12:00:00',
		);
		// User 99 not in store.

		$result = Tsubakuro_Admin::get_comment(5);

		$this->assertSame('不明', $result['user_name']);
	}

	// -------------------------------------------------------------------------
	// get_task_comments()
	// -------------------------------------------------------------------------

	public function test_get_task_comments_returns_empty_array_when_no_rows(): void
	{
		$result = Tsubakuro_Admin::get_task_comments(101);

		$this->assertSame(array(), $result);
	}

	public function test_get_task_comments_returns_formatted_comment_list(): void
	{
		$GLOBALS['tsubakuro_test']['posts'] = array(
			1 => (object) array(
				'ID'            => 1,
				'post_type'     => Tsubakuro_Post_Types::COMMENT_POST_TYPE,
				'post_author'   => 7,
				'post_parent'   => 101,
				'post_content'  => 'First',
				'post_date'     => '2026-05-01 09:00:00',
				'post_modified' => '2026-05-01 09:00:00',
			),
			2 => (object) array(
				'ID'            => 2,
				'post_type'     => Tsubakuro_Post_Types::COMMENT_POST_TYPE,
				'post_author'   => 0,
				'post_parent'   => 101,
				'post_content'  => 'Second',
				'post_date'     => '2026-05-01 10:00:00',
				'post_modified' => '2026-05-01 10:00:00',
			),
			3 => (object) array(
				'ID'            => 3,
				'post_type'     => Tsubakuro_Post_Types::COMMENT_POST_TYPE,
				'post_author'   => 7,
				'post_parent'   => 999,
				'post_content'  => 'Other task comment',
				'post_date'     => '2026-05-01 11:00:00',
				'post_modified' => '2026-05-01 11:00:00',
			),
			100 => (object) array(
				'ID'            => 100,
				'post_type'     => 'post',
				'post_author'   => 7,
				'post_content'  => 'Ignored regular post',
				'post_date'     => '2026-05-01 11:30:00',
				'post_modified' => '2026-05-01 11:30:00',
			),
		);
		$GLOBALS['tsubakuro_test']['users'][7] = (object) array(
			'ID'           => 7,
			'display_name' => 'Dave',
		);

		$result = Tsubakuro_Admin::get_task_comments(101);

		$this->assertCount(2, $result);
		$this->assertSame(1, $result[0]['id']);
		$this->assertSame('Dave', $result[0]['user_name']);
		$this->assertSame('不明', $result[1]['user_name']);
		$this->assertSame('Second', $result[1]['comment']);
	}

	// -------------------------------------------------------------------------
	// exclude_task_comments_from_list()
	// -------------------------------------------------------------------------

	public function test_init_registers_pre_get_comments_hook(): void
	{
		Tsubakuro_Admin::init();

		$this->assertContains(
			array('Tsubakuro_Admin', 'exclude_task_comments_from_list'),
			$GLOBALS['tsubakuro_test']['actions']['pre_get_comments'] ?? array()
		);
	}

	public function test_exclude_task_comments_adds_type_not_in_on_admin_comments_page(): void
	{
		global $pagenow;
		$GLOBALS['tsubakuro_test']['is_admin'] = true;
		$pagenow                               = 'edit-comments.php';

		$query             = new WP_Comment_Query();
		$query->query_vars = array();

		Tsubakuro_Admin::exclude_task_comments_from_list($query);

		$this->assertArrayHasKey('type__not_in', $query->query_vars);
		$this->assertContains(Tsubakuro_Admin::COMMENT_TYPE, $query->query_vars['type__not_in']);
	}

	public function test_exclude_task_comments_preserves_existing_type_not_in(): void
	{
		global $pagenow;
		$GLOBALS['tsubakuro_test']['is_admin'] = true;
		$pagenow                               = 'edit-comments.php';

		$query             = new WP_Comment_Query();
		$query->query_vars = array('type__not_in' => array('ping'));

		Tsubakuro_Admin::exclude_task_comments_from_list($query);

		$this->assertContains('ping', $query->query_vars['type__not_in']);
		$this->assertContains(Tsubakuro_Admin::COMMENT_TYPE, $query->query_vars['type__not_in']);
	}

	public function test_exclude_task_comments_applies_outside_admin(): void
	{
		$GLOBALS['tsubakuro_test']['is_admin'] = false;

		$query             = new WP_Comment_Query();
		$query->query_vars = array();

		Tsubakuro_Admin::exclude_task_comments_from_list($query);

		$this->assertArrayHasKey('type__not_in', $query->query_vars);
		$this->assertContains(Tsubakuro_Admin::COMMENT_TYPE, $query->query_vars['type__not_in']);
	}

	public function test_exclude_task_comments_applies_on_admin_dashboard(): void
	{
		global $pagenow;
		$GLOBALS['tsubakuro_test']['is_admin'] = true;
		$pagenow                               = 'index.php';

		$query             = new WP_Comment_Query();
		$query->query_vars = array();

		Tsubakuro_Admin::exclude_task_comments_from_list($query);

		$this->assertArrayHasKey('type__not_in', $query->query_vars);
		$this->assertContains(Tsubakuro_Admin::COMMENT_TYPE, $query->query_vars['type__not_in']);
	}

	public function test_exclude_task_comments_does_nothing_for_plugin_own_query(): void
	{
		$query             = new WP_Comment_Query();
		$query->query_vars = array('type' => Tsubakuro_Admin::COMMENT_TYPE);

		Tsubakuro_Admin::exclude_task_comments_from_list($query);

		$this->assertArrayNotHasKey('type__not_in', $query->query_vars);
	}
}
