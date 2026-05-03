<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Tsubakuro_REST_API handlers and permission callbacks.
 */
class RestApiTest extends TestCase {

	protected function setUp(): void {
		tsubakuro_test_reset();
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	private function make_post( int $id, string $title, string $content = '' ): object {
		return (object) array(
			'ID'            => $id,
			'post_type'     => 'tsubakuro_task',
			'post_title'    => $title,
			'post_content'  => $content,
			'post_date'     => '2026-05-01 10:00:00',
			'post_modified' => '2026-05-01 11:00:00',
			'post_author'   => 7,
		);
	}

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	public function test_check_read_permission_returns_true_when_user_can_edit_posts(): void {
		$this->assertTrue( Tsubakuro_REST_API::check_read_permission() );
	}

	public function test_check_read_permission_returns_false_when_user_cannot(): void {
		$GLOBALS['tsubakuro_test']['can']['edit_posts'] = false;
		$this->assertFalse( Tsubakuro_REST_API::check_read_permission() );
	}

	public function test_check_write_permission_requires_edit_posts(): void {
		$this->assertTrue( Tsubakuro_REST_API::check_write_permission() );

		$GLOBALS['tsubakuro_test']['can']['edit_posts'] = false;
		$this->assertFalse( Tsubakuro_REST_API::check_write_permission() );
	}

	public function test_check_delete_permission_requires_delete_posts(): void {
		$this->assertTrue( Tsubakuro_REST_API::check_delete_permission() );

		$GLOBALS['tsubakuro_test']['can']['delete_posts'] = false;
		$this->assertFalse( Tsubakuro_REST_API::check_delete_permission() );
	}

	// -------------------------------------------------------------------------
	// GET /tasks
	// -------------------------------------------------------------------------

	public function test_get_tasks_handler_passes_filters_to_post_types(): void {
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post( 1, 'Task A' );
		$GLOBALS['tsubakuro_test']['post_meta'][1] = array(
			'_tsubakuro_status' => array( 'todo' ),
		);

		$req    = new WP_REST_Request( array( 'status' => 'todo', 'per_page' => 10 ) );
		$result = Tsubakuro_REST_API::get_tasks( $req );

		$this->assertCount( 1, $result );
		$this->assertSame( 10, $GLOBALS['tsubakuro_test']['last_query_args']['posts_per_page'] );
		$this->assertSame(
			'_tsubakuro_status',
			$GLOBALS['tsubakuro_test']['last_query_args']['meta_query'][0]['key']
		);
	}

	public function test_get_tasks_handler_without_filters_returns_all_tasks(): void {
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post( 1, 'Task A' );
		$GLOBALS['tsubakuro_test']['posts'][2] = $this->make_post( 2, 'Task B' );

		$req    = new WP_REST_Request( array() );
		$result = Tsubakuro_REST_API::get_tasks( $req );

		$this->assertCount( 2, $result );
	}

	// -------------------------------------------------------------------------
	// POST /tasks
	// -------------------------------------------------------------------------

	public function test_create_task_saves_meta_and_returns_task(): void {
		// Pre-seed the post that wp_insert_post() will "create" (always returns 123).
		$GLOBALS['tsubakuro_test']['posts'][123] = $this->make_post( 123, 'New Task', 'body' );

		$req    = new WP_REST_Request(
			array(
				'title'         => 'New Task',
				'content'       => 'body',
				'status'        => 'todo',
				'assignee'      => 0,
				'related_pages' => array(),
			)
		);
		$result = Tsubakuro_REST_API::create_task( $req );

		$this->assertSame( 123, $result['id'] );
		$this->assertArrayHasKey( '_tsubakuro_status', $GLOBALS['tsubakuro_test']['post_meta'][123] );
	}

	// -------------------------------------------------------------------------
	// GET /tasks/{id}
	// -------------------------------------------------------------------------

	public function test_get_task_handler_returns_wp_error_when_not_found(): void {
		$req    = new WP_REST_Request( array( 'id' => 999 ) );
		$result = Tsubakuro_REST_API::get_task( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'not_found', $result->get_error_code() );
	}

	public function test_get_task_handler_returns_task_with_comments(): void {
		$GLOBALS['tsubakuro_test']['posts'][101]  = $this->make_post( 101, 'Task X', 'Body' );
		$GLOBALS['tsubakuro_test']['users'][7]    = (object) array(
			'ID'           => 7,
			'display_name' => 'Author',
		);
		$GLOBALS['tsubakuro_test']['comments'][1] = (object) array(
			'comment_ID'       => 1,
			'comment_post_ID'  => 101,
			'user_id'          => 7,
			'comment_content'  => 'First comment',
			'comment_type'     => Tsubakuro_Admin::COMMENT_TYPE,
			'comment_approved' => 1,
			'comment_date'     => '2026-05-01 12:00:00',
		);

		$req    = new WP_REST_Request( array( 'id' => 101 ) );
		$result = Tsubakuro_REST_API::get_task( $req );

		$this->assertSame( 101, $result['id'] );
		$this->assertArrayHasKey( 'comments', $result );
		$this->assertCount( 1, $result['comments'] );
		$this->assertSame( 'First comment', $result['comments'][0]['comment'] );
	}

	// -------------------------------------------------------------------------
	// PUT /tasks/{id}
	// -------------------------------------------------------------------------

	public function test_update_task_handler_returns_wp_error_when_not_found(): void {
		$req    = new WP_REST_Request( array( 'id' => 999, 'title' => 'X' ) );
		$result = Tsubakuro_REST_API::update_task( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'not_found', $result->get_error_code() );
	}

	public function test_update_task_handler_updates_title_and_returns_task(): void {
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_post( 101, 'Old Title', '' );

		$req    = new WP_REST_Request(
			array(
				'id'    => 101,
				'title' => 'New Title',
			)
		);
		$result = Tsubakuro_REST_API::update_task( $req );

		// The mock wp_update_post is a no-op; we just verify no error was returned.
		$this->assertFalse( is_wp_error( $result ) );
		$this->assertSame( 101, $result['id'] );
	}

	public function test_update_task_handler_saves_meta_when_provided(): void {
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_post( 101, 'Task', '' );

		$req = new WP_REST_Request(
			array(
				'id'     => 101,
				'status' => 'completed',
			)
		);
		Tsubakuro_REST_API::update_task( $req );

		$this->assertSame(
			array( 'completed' ),
			$GLOBALS['tsubakuro_test']['post_meta'][101]['_tsubakuro_status']
		);
	}

	// -------------------------------------------------------------------------
	// DELETE /tasks/{id}
	// -------------------------------------------------------------------------

	public function test_delete_task_handler_returns_wp_error_when_not_found(): void {
		$req    = new WP_REST_Request( array( 'id' => 999 ) );
		$result = Tsubakuro_REST_API::delete_task( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'not_found', $result->get_error_code() );
	}

	public function test_delete_task_handler_returns_deleted_confirmation(): void {
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_post( 101, 'Task', '' );

		$req    = new WP_REST_Request( array( 'id' => 101 ) );
		$result = Tsubakuro_REST_API::delete_task( $req );

		$this->assertTrue( $result['deleted'] );
		$this->assertSame( 101, $result['id'] );
	}

	// -------------------------------------------------------------------------
	// GET /tasks/{id}/comments
	// -------------------------------------------------------------------------

	public function test_get_comments_handler_returns_wp_error_when_task_not_found(): void {
		$req    = new WP_REST_Request( array( 'id' => 999 ) );
		$result = Tsubakuro_REST_API::get_comments( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'not_found', $result->get_error_code() );
	}

	public function test_get_comments_handler_returns_comment_list(): void {
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_post( 101, 'Task', '' );
		$GLOBALS['tsubakuro_test']['comments'][1] = (object) array(
			'comment_ID'       => 1,
			'comment_post_ID'  => 101,
			'user_id'          => 0,
			'comment_content'  => 'Hello',
			'comment_type'     => Tsubakuro_Admin::COMMENT_TYPE,
			'comment_approved' => 1,
			'comment_date'     => '2026-05-01 09:00:00',
		);

		$req    = new WP_REST_Request( array( 'id' => 101 ) );
		$result = Tsubakuro_REST_API::get_comments( $req );

		$this->assertCount( 1, $result );
		$this->assertSame( 'Hello', $result[0]['comment'] );
	}

	// -------------------------------------------------------------------------
	// POST /tasks/{id}/comments
	// -------------------------------------------------------------------------

	public function test_add_comment_handler_returns_wp_error_when_task_not_found(): void {
		$req    = new WP_REST_Request( array( 'id' => 999, 'comment' => 'Hi' ) );
		$result = Tsubakuro_REST_API::add_comment( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'not_found', $result->get_error_code() );
	}

	public function test_add_comment_handler_returns_wp_error_on_insert_failure(): void {
		$GLOBALS['tsubakuro_test']['posts'][101]          = $this->make_post( 101, 'Task', '' );
		$GLOBALS['tsubakuro_test']['wpdb_insert_fail']    = true;

		$req    = new WP_REST_Request( array( 'id' => 101, 'comment' => 'Hi' ) );
		$result = Tsubakuro_REST_API::add_comment( $req );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'insert_failed', $result->get_error_code() );
	}

	public function test_add_comment_handler_returns_inserted_comment(): void {
		$GLOBALS['tsubakuro_test']['posts'][101] = $this->make_post( 101, 'Task', '' );
		$GLOBALS['tsubakuro_test']['users'][7]   = (object) array(
			'ID'           => 7,
			'display_name' => 'Alice',
		);
		$req    = new WP_REST_Request( array( 'id' => 101, 'comment' => 'My comment' ) );
		$result = Tsubakuro_REST_API::add_comment( $req );

		$this->assertSame( 1, $result['id'] );
		$this->assertSame( 'My comment', $result['comment'] );
		$this->assertSame( 'Alice', $result['user_name'] );
	}
}
