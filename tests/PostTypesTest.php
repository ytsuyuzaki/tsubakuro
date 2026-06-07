<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Tsubakuro_Post_Types – edge cases not covered by TsubakuroTest.
 */
class PostTypesTest extends TestCase
{

	protected function setUp(): void
	{
		tsubakuro_test_reset();
	}

	// -------------------------------------------------------------------------
	// make_post helper
	// -------------------------------------------------------------------------

	private function make_post(int $id, string $title, string $content, string $type = 'tsubakuro_task'): object
	{
		return (object) array(
			'ID'            => $id,
			'post_type'     => $type,
			'post_title'    => $title,
			'post_content'  => $content,
			'post_date'     => '2026-05-01 10:00:00',
			'post_modified' => '2026-05-01 11:00:00',
			'post_author'   => 1,
		);
	}

	// -------------------------------------------------------------------------
	// get_task()
	// -------------------------------------------------------------------------

	public function test_get_task_returns_null_for_missing_post(): void
	{
		$result = Tsubakuro_Post_Types::get_task(999);

		$this->assertNull($result);
	}

	public function test_get_task_returns_null_for_wrong_post_type(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][50] = $this->make_post(50, 'A page', 'content', 'page');

		$result = Tsubakuro_Post_Types::get_task(50);

		$this->assertNull($result);
	}

	public function test_get_task_returns_formatted_task_for_valid_post(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][42] = $this->make_post(42, 'My Task', 'Body text');

		$task = Tsubakuro_Post_Types::get_task(42);

		$this->assertIsArray($task);
		$this->assertSame(42, $task['id']);
		$this->assertSame('My Task', $task['title']);
		$this->assertSame('Body text', $task['content']);
	}

	// -------------------------------------------------------------------------
	// save_meta()
	// -------------------------------------------------------------------------

	public function test_save_meta_ignores_unrecognized_status(): void
	{
		Tsubakuro_Post_Types::save_meta(101, array('status' => 'invalid_status'));

		$this->assertArrayNotHasKey(
			'_tsubakuro_status',
			$GLOBALS['tsubakuro_test']['post_meta'][101] ?? array()
		);
	}

	public function test_save_meta_stores_all_three_statuses(): void
	{
		foreach (array('todo', 'in_progress', 'completed') as $status) {
			tsubakuro_test_reset();
			Tsubakuro_Post_Types::save_meta(1, array('status' => $status));
			$this->assertSame(
				array($status),
				$GLOBALS['tsubakuro_test']['post_meta'][1]['_tsubakuro_status'],
				"Expected status '$status' to be stored."
			);
		}
	}

	public function test_save_meta_ignores_unrecognized_priority(): void
	{
		Tsubakuro_Post_Types::save_meta(101, array('priority' => 'invalid_priority'));

		$this->assertArrayNotHasKey(
			'_tsubakuro_priority',
			$GLOBALS['tsubakuro_test']['post_meta'][101] ?? array()
		);
	}

	public function test_save_meta_stores_all_three_priorities(): void
	{
		foreach (array('low', 'medium', 'high') as $priority) {
			tsubakuro_test_reset();
			Tsubakuro_Post_Types::save_meta(1, array('priority' => $priority));
			$this->assertSame(
				array($priority),
				$GLOBALS['tsubakuro_test']['post_meta'][1]['_tsubakuro_priority'],
				"Expected priority '$priority' to be stored."
			);
		}
	}

	public function test_save_meta_accepts_comma_separated_related_pages_string(): void
	{
		Tsubakuro_Post_Types::save_meta(101, array('related_pages' => '10,20,30'));

		$this->assertSame(
			array(10, 20, 30),
			$GLOBALS['tsubakuro_test']['post_meta'][101]['_tsubakuro_related_page']
		);
	}

	public function test_save_meta_deduplicates_related_pages(): void
	{
		Tsubakuro_Post_Types::save_meta(101, array('related_pages' => array(5, 5, 10)));

		$this->assertSame(
			array(5, 10),
			$GLOBALS['tsubakuro_test']['post_meta'][101]['_tsubakuro_related_page']
		);
	}

	public function test_save_meta_skips_absent_fields(): void
	{
		Tsubakuro_Post_Types::save_meta(101, array());

		$this->assertEmpty($GLOBALS['tsubakuro_test']['post_meta'][101] ?? array());
	}

	public function test_save_meta_stores_reminder_datetimes(): void
	{
		Tsubakuro_Post_Types::save_meta(
			101,
			array(
				'start_remind_at' => '2026-06-08 09:00:00',
				'due_remind_at'   => '2026-06-09 18:00:00',
			)
		);

		$this->assertSame(
			array('2026-06-08 09:00:00'),
			$GLOBALS['tsubakuro_test']['post_meta'][101]['_tsubakuro_start_remind_at']
		);
		$this->assertSame(
			array('2026-06-09 18:00:00'),
			$GLOBALS['tsubakuro_test']['post_meta'][101]['_tsubakuro_due_remind_at']
		);
	}

	public function test_save_meta_clears_sent_flag_when_reminder_datetime_changes(): void
	{
		$GLOBALS['tsubakuro_test']['post_meta'][101]['_tsubakuro_start_remind_at']  = array('2026-06-08 09:00:00');
		$GLOBALS['tsubakuro_test']['post_meta'][101]['_tsubakuro_start_reminded_at'] = array('2026-06-08 09:05:00');

		Tsubakuro_Post_Types::save_meta(101, array('start_remind_at' => '2026-06-08 10:00:00'));

		$this->assertSame(
			array('2026-06-08 10:00:00'),
			$GLOBALS['tsubakuro_test']['post_meta'][101]['_tsubakuro_start_remind_at']
		);
		$this->assertArrayNotHasKey(
			'_tsubakuro_start_reminded_at',
			$GLOBALS['tsubakuro_test']['post_meta'][101]
		);
	}

	// -------------------------------------------------------------------------
	// format_task()
	// -------------------------------------------------------------------------

	public function test_format_task_defaults_status_to_todo_when_meta_missing(): void
	{
		$GLOBALS['tsubakuro_test']['post_meta'][10] = array();

		$task = Tsubakuro_Post_Types::format_task($this->make_post(10, 'T', 'C'));

		$this->assertSame('todo', $task['status']);
		$this->assertSame('ToDo', $task['status_label']);
	}

	public function test_format_task_defaults_priority_to_medium_when_meta_missing(): void
	{
		$GLOBALS['tsubakuro_test']['post_meta'][10] = array();

		$task = Tsubakuro_Post_Types::format_task($this->make_post(10, 'T', 'C'));

		$this->assertSame('medium', $task['priority']);
		$this->assertSame('中', $task['priority_label']);
	}

	public function test_format_task_returns_correct_priority_label(): void
	{
		$GLOBALS['tsubakuro_test']['post_meta'][10] = array(
			'_tsubakuro_priority' => array('high'),
		);

		$task = Tsubakuro_Post_Types::format_task($this->make_post(10, 'T', 'C'));

		$this->assertSame('high', $task['priority']);
		$this->assertSame('高', $task['priority_label']);
	}

	public function test_format_task_returns_null_assignee_when_not_set(): void
	{
		$GLOBALS['tsubakuro_test']['post_meta'][10] = array();

		$task = Tsubakuro_Post_Types::format_task($this->make_post(10, 'T', 'C'));

		$this->assertNull($task['assignee']);
	}

	public function test_format_task_returns_null_assignee_when_user_not_found(): void
	{
		$GLOBALS['tsubakuro_test']['post_meta'][10] = array(
			'_tsubakuro_assignee' => array(99),
		);
		// User 99 is not in the users store.

		$task = Tsubakuro_Post_Types::format_task($this->make_post(10, 'T', 'C'));

		$this->assertNull($task['assignee']);
	}

	public function test_format_task_uses_raw_status_as_label_for_unknown_status(): void
	{
		$GLOBALS['tsubakuro_test']['post_meta'][10] = array(
			'_tsubakuro_status' => array('custom_status'),
		);

		$task = Tsubakuro_Post_Types::format_task($this->make_post(10, 'T', 'C'));

		$this->assertSame('custom_status', $task['status']);
		$this->assertSame('custom_status', $task['status_label']);
	}

	public function test_format_task_maps_post_dates_and_author(): void
	{
		$post               = $this->make_post(10, 'T', 'C');
		$post->post_date    = '2026-01-01 00:00:00';
		$post->post_modified = '2026-02-01 00:00:00';
		$post->post_author  = 3;

		$task = Tsubakuro_Post_Types::format_task($post);

		$this->assertSame('2026-01-01 00:00:00', $task['created_at']);
		$this->assertSame('2026-02-01 00:00:00', $task['updated_at']);
		$this->assertSame(3, $task['author_id']);
	}

	public function test_format_task_includes_reminder_fields(): void
	{
		$GLOBALS['tsubakuro_test']['post_meta'][10] = array(
			'_tsubakuro_start_remind_at' => array('2026-06-10 09:00:00'),
			'_tsubakuro_due_remind_at'   => array('2026-06-11 17:00:00'),
		);

		$task = Tsubakuro_Post_Types::format_task($this->make_post(10, 'T', 'C'));

		$this->assertSame('2026-06-10 09:00:00', $task['start_remind_at']);
		$this->assertSame('2026-06-11 17:00:00', $task['due_remind_at']);
	}

	// -------------------------------------------------------------------------
	// get_tasks()
	// -------------------------------------------------------------------------

	public function test_get_tasks_with_no_filters_uses_default_query_args(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post(1, 'Task A', 'Body');

		$tasks = Tsubakuro_Post_Types::get_tasks();

		$args = $GLOBALS['tsubakuro_test']['last_query_args'];
		$this->assertSame('tsubakuro_task', $args['post_type']);
		$this->assertSame(50, $args['posts_per_page']);
		$this->assertArrayNotHasKey('meta_query', $args);
		$this->assertCount(1, $tasks);
	}

	public function test_get_tasks_with_related_page_filter_adds_numeric_meta_query(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post(1, 'T', 'C');

		Tsubakuro_Post_Types::get_tasks(array('related_page' => 7));

		$meta_query = $GLOBALS['tsubakuro_test']['last_query_args']['meta_query'];
		$keys       = array_column($meta_query, 'key');
		$this->assertContains('_tsubakuro_related_page', $keys);

		$page_filter = array_filter($meta_query, fn($m) => '_tsubakuro_related_page' === $m['key']);
		$page_filter = array_values($page_filter)[0];
		$this->assertSame(7, $page_filter['value']);
		$this->assertSame('NUMERIC', $page_filter['type']);
	}

	public function test_get_tasks_custom_per_page_overrides_default(): void
	{
		Tsubakuro_Post_Types::get_tasks(array('posts_per_page' => 5));

		$this->assertSame(5, $GLOBALS['tsubakuro_test']['last_query_args']['posts_per_page']);
	}

	public function test_get_tasks_with_search_and_assignee_filter_builds_query(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post(1, 'Urgent task', 'Body');
		$GLOBALS['tsubakuro_test']['post_meta'][1] = array(
			'_tsubakuro_assignee' => array(7),
		);

		$tasks = Tsubakuro_Post_Types::get_tasks(
			array(
				's'        => 'urgent',
				'assignee' => 7,
			)
		);

		$args = $GLOBALS['tsubakuro_test']['last_query_args'];
		$this->assertSame('urgent', $args['s']);
		$this->assertSame('_tsubakuro_assignee', $args['meta_query'][0]['key']);
		$this->assertSame(7, $args['meta_query'][0]['value']);
		$this->assertCount(1, $tasks);
	}

	public function test_get_tasks_sorts_by_supported_columns(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][2] = $this->make_post(2, 'Beta', 'Body');
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post(1, 'Alpha', 'Body');

		$tasks = Tsubakuro_Post_Types::get_tasks(
			array(
				'orderby' => 'title',
				'order'   => 'ASC',
			)
		);

		$args = $GLOBALS['tsubakuro_test']['last_query_args'];
		$this->assertSame('title', $args['orderby']);
		$this->assertSame('ASC', $args['order']);
		$this->assertSame(array('Alpha', 'Beta'), array_column($tasks, 'title'));
	}

	public function test_get_tasks_sorts_by_status_meta_value(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post(1, 'Task A', 'Body');
		$GLOBALS['tsubakuro_test']['posts'][2] = $this->make_post(2, 'Task B', 'Body');
		$GLOBALS['tsubakuro_test']['post_meta'][1] = array(
			'_tsubakuro_status' => array('todo'),
		);
		$GLOBALS['tsubakuro_test']['post_meta'][2] = array(
			'_tsubakuro_status' => array('completed'),
		);

		Tsubakuro_Post_Types::get_tasks(
			array(
				'orderby' => 'status',
				'order'   => 'ASC',
			)
		);

		$args = $GLOBALS['tsubakuro_test']['last_query_args'];
		$this->assertSame('meta_value', $args['orderby']);
		$this->assertSame('_tsubakuro_status', $args['meta_key']);
	}

	public function test_get_tasks_with_priority_filter_adds_meta_query(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post(1, 'Task A', 'Body');
		$GLOBALS['tsubakuro_test']['post_meta'][1] = array(
			'_tsubakuro_priority' => array('high'),
		);

		Tsubakuro_Post_Types::get_tasks(array('priority' => 'high'));

		$meta_query = $GLOBALS['tsubakuro_test']['last_query_args']['meta_query'];
		$keys       = array_column($meta_query, 'key');
		$this->assertContains('_tsubakuro_priority', $keys);
	}

	public function test_get_tasks_todo_filter_includes_tasks_with_missing_status_meta(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post(1, 'Task A', 'Body');
		$GLOBALS['tsubakuro_test']['posts'][2] = $this->make_post(2, 'Task B', 'Body');
		$GLOBALS['tsubakuro_test']['posts'][3] = $this->make_post(3, 'Task C', 'Body');

		$GLOBALS['tsubakuro_test']['post_meta'][2] = array(
			'_tsubakuro_status' => array('todo'),
		);
		$GLOBALS['tsubakuro_test']['post_meta'][3] = array(
			'_tsubakuro_status' => array('completed'),
		);

		$tasks = Tsubakuro_Post_Types::get_tasks(
			array(
				'status'  => 'todo',
				'orderby' => 'id',
				'order'   => 'ASC',
			)
		);

		$this->assertSame(array(1, 2), array_column($tasks, 'id'));
		$this->assertArrayNotHasKey('meta_query', $GLOBALS['tsubakuro_test']['last_query_args']);
	}

	public function test_get_tasks_sorts_by_priority_meta_value(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post(1, 'Task A', 'Body');

		Tsubakuro_Post_Types::get_tasks(
			array(
				'orderby' => 'priority',
				'order'   => 'ASC',
			)
		);

		$args = $GLOBALS['tsubakuro_test']['last_query_args'];
		$this->assertSame('meta_value', $args['orderby']);
		$this->assertSame('_tsubakuro_priority', $args['meta_key']);
	}

	// -------------------------------------------------------------------------
	// Parent / child task support
	// -------------------------------------------------------------------------

	public function test_format_task_includes_parent_id_zero_for_root_task(): void
	{
		$task = Tsubakuro_Post_Types::format_task($this->make_post(10, 'Root', 'Content'));

		$this->assertArrayHasKey('parent_id', $task);
		$this->assertSame(0, $task['parent_id']);
	}

	public function test_format_task_returns_correct_parent_id_when_set(): void
	{
		$post = $this->make_post(20, 'Child', 'Content');
		$post->post_parent = 10;

		$task = Tsubakuro_Post_Types::format_task($post);

		$this->assertSame(10, $task['parent_id']);
	}

	public function test_get_tasks_with_parent_id_filter_sets_post_parent_in_query(): void
	{
		$parent = $this->make_post(5, 'Parent', 'Body');
		$child  = $this->make_post(6, 'Child', 'Body');
		$child->post_parent = 5;
		$GLOBALS['tsubakuro_test']['posts'][5] = $parent;
		$GLOBALS['tsubakuro_test']['posts'][6] = $child;

		$tasks = Tsubakuro_Post_Types::get_tasks(array('parent_id' => 5));

		$args = $GLOBALS['tsubakuro_test']['last_query_args'];
		$this->assertSame(5, $args['post_parent']);
		$this->assertCount(1, $tasks);
		$this->assertSame(6, $tasks[0]['id']);
	}

	public function test_get_tasks_with_parent_id_zero_returns_root_tasks_only(): void
	{
		$root  = $this->make_post(1, 'Root', 'Body');
		$child = $this->make_post(2, 'Child', 'Body');
		$child->post_parent = 1;
		$GLOBALS['tsubakuro_test']['posts'][1] = $root;
		$GLOBALS['tsubakuro_test']['posts'][2] = $child;

		$tasks = Tsubakuro_Post_Types::get_tasks(array('parent_id' => 0));

		$args = $GLOBALS['tsubakuro_test']['last_query_args'];
		$this->assertSame(0, $args['post_parent']);
		// WP_Query stub only matches posts whose post_parent equals the filter value.
		$this->assertCount(1, $tasks);
		$this->assertSame(1, $tasks[0]['id']);
	}
}
