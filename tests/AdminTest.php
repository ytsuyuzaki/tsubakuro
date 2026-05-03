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
