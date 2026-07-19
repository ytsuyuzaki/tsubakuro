<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Tsubakuro_Evaluations data layer.
 */
class EvaluationsTest extends TestCase
{

	protected function setUp(): void
	{
		tsubakuro_test_reset();
		$_GET = array();
	}

	private function make_post(int $id, string $title = 'Eval', string $content = '', string $type = 'tsubakuro_evaluation', string $status = 'publish'): object
	{
		return (object) array(
			'ID'            => $id,
			'post_type'     => $type,
			'post_status'   => $status,
			'post_title'    => $title,
			'post_content'  => $content,
			'post_date'     => '2026-05-01 10:00:00',
			'post_modified' => '2026-05-01 11:00:00',
			'post_author'   => 3,
		);
	}

	private function make_insight_post(int $id, string $title = 'Insight'): object
	{
		return (object) array(
			'ID'            => $id,
			'post_type'     => 'tsubakuro_insight',
			'post_title'    => $title,
			'post_content'  => '',
			'post_date'     => '2026-05-01 10:00:00',
			'post_modified' => '2026-05-01 11:00:00',
			'post_author'   => 3,
		);
	}

	public function test_get_evaluation_returns_null_for_missing_post(): void
	{
		$this->assertNull(Tsubakuro_Evaluations::get_evaluation(999));
	}

	public function test_get_evaluation_returns_null_for_wrong_post_type(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][10] = $this->make_post(10, 'A task', '', 'tsubakuro_task');
		$this->assertNull(Tsubakuro_Evaluations::get_evaluation(10));
	}

	public function test_save_meta_persists_all_recognized_fields(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][5] = $this->make_post(5);

		Tsubakuro_Evaluations::save_meta(5, array(
			'target_post'    => 42,
			'change_item'    => 'comparison',
			'metric'         => 'search_rank',
			'purpose'        => '順位向上',
			'implemented_at' => '2026-05-01',
			'due_at'         => '2026-05-15',
			'before_value'   => '8位',
			'after_value'    => '3位',
			'result'         => '上昇した',
			'judgment'       => 'success',
			'note'           => '継続観察',
		));

		$eval = Tsubakuro_Evaluations::get_evaluation(5);

		$this->assertSame(42, $eval['target_post_id']);
		$this->assertSame('comparison', $eval['change_item']);
		$this->assertSame('比較表追加', $eval['change_item_label']);
		$this->assertSame('search_rank', $eval['metric']);
		$this->assertSame('検索順位', $eval['metric_label']);
		$this->assertSame('順位向上', $eval['purpose']);
		$this->assertSame('2026-05-01', $eval['implemented_at']);
		$this->assertSame('2026-05-15', $eval['due_at']);
		$this->assertSame('8位', $eval['before_value']);
		$this->assertSame('3位', $eval['after_value']);
		$this->assertSame('success', $eval['judgment']);
		$this->assertSame('成功', $eval['judgment_label']);
		$this->assertTrue($eval['is_evaluated']);
	}

	public function test_save_meta_rejects_unknown_enum_values(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][6] = $this->make_post(6);

		Tsubakuro_Evaluations::save_meta(6, array(
			'change_item' => 'bogus',
			'metric'      => 'bogus',
			'judgment'    => 'bogus',
		));

		$eval = Tsubakuro_Evaluations::get_evaluation(6);
		$this->assertSame('', $eval['change_item']);
		$this->assertSame('', $eval['metric']);
		$this->assertSame('', $eval['judgment']);
		$this->assertFalse($eval['is_evaluated']);
	}

	public function test_empty_judgment_marks_unevaluated(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][7] = $this->make_post(7);
		Tsubakuro_Evaluations::save_meta(7, array('judgment' => ''));

		$eval = Tsubakuro_Evaluations::get_evaluation(7);
		$this->assertFalse($eval['is_evaluated']);
		$this->assertSame('', $eval['judgment_label']);
	}

	public function test_clearing_judgment_resets_reminder_flag(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][8] = $this->make_post(8);
		Tsubakuro_Evaluations::save_meta(8, array('judgment' => 'success'));
		update_post_meta(8, '_tsubakuro_eval_reminded_at', '2026-05-01 00:00:00');

		Tsubakuro_Evaluations::save_meta(8, array('judgment' => ''));

		$this->assertSame('', get_post_meta(8, '_tsubakuro_eval_reminded_at', true));
	}

	public function test_get_evaluations_filters_unevaluated(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post(1, 'Done');
		$GLOBALS['tsubakuro_test']['posts'][2] = $this->make_post(2, 'Pending');
		Tsubakuro_Evaluations::save_meta(1, array('judgment' => 'success'));
		Tsubakuro_Evaluations::save_meta(2, array('purpose' => 'x'));

		$unevaluated = Tsubakuro_Evaluations::get_evaluations(array('unevaluated' => true));

		$ids = array_column($unevaluated, 'id');
		$this->assertContains(2, $ids);
		$this->assertNotContains(1, $ids);
	}

	public function test_get_evaluations_includes_pending_posts_in_internal_lists(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post(1, 'Published');
		$GLOBALS['tsubakuro_test']['posts'][2] = $this->make_post(2, 'Pending Review', '', 'tsubakuro_evaluation', 'pending');

		$result = Tsubakuro_Evaluations::get_evaluations();

		$ids = array_column($result, 'id');
		$this->assertContains(1, $ids);
		$this->assertContains(2, $ids);
	}

	public function test_empty_change_item_clears_previously_set_value(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][9] = $this->make_post(9);
		Tsubakuro_Evaluations::save_meta(9, array('change_item' => 'comparison'));
		$this->assertSame('comparison', Tsubakuro_Evaluations::get_evaluation(9)['change_item']);

		Tsubakuro_Evaluations::save_meta(9, array('change_item' => ''));
		$this->assertSame('', Tsubakuro_Evaluations::get_evaluation(9)['change_item']);
	}

	public function test_date_only_value_is_stored_verbatim_without_timezone_shift(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][11] = $this->make_post(11);
		Tsubakuro_Evaluations::save_meta(11, array('due_at' => '2026-05-01', 'implemented_at' => '2026-01-31'));

		$eval = Tsubakuro_Evaluations::get_evaluation(11);
		$this->assertSame('2026-05-01', $eval['due_at']);
		$this->assertSame('2026-01-31', $eval['implemented_at']);
	}

	public function test_invalid_date_is_rejected(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][12] = $this->make_post(12);
		Tsubakuro_Evaluations::save_meta(12, array('due_at' => '2026-13-40'));

		$this->assertSame('', Tsubakuro_Evaluations::get_evaluation(12)['due_at']);
	}

	public function test_get_evaluations_filters_by_target_post(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post(1, 'A');
		$GLOBALS['tsubakuro_test']['posts'][2] = $this->make_post(2, 'B');
		Tsubakuro_Evaluations::save_meta(1, array('target_post' => 100));
		Tsubakuro_Evaluations::save_meta(2, array('target_post' => 200));

		$result = Tsubakuro_Evaluations::get_evaluations(array('target_post' => 100));

		$this->assertCount(1, $result);
		$this->assertSame(1, $result[0]['id']);
	}

	public function test_get_evaluations_filters_by_implemented_and_due_dates(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post(1, 'A');
		$GLOBALS['tsubakuro_test']['posts'][2] = $this->make_post(2, 'B');
		Tsubakuro_Evaluations::save_meta(1, array('implemented_at' => '2026-05-01', 'due_at' => '2026-05-15'));
		Tsubakuro_Evaluations::save_meta(2, array('implemented_at' => '2026-05-02', 'due_at' => '2026-05-15'));

		$result = Tsubakuro_Evaluations::get_evaluations(array(
			'implemented_at' => '2026-05-01',
			'due_at'         => '2026-05-15',
		));

		$this->assertCount(1, $result);
		$this->assertSame(1, $result[0]['id']);
	}

	public function test_get_evaluations_filters_by_include_ids(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post(1, 'A');
		$GLOBALS['tsubakuro_test']['posts'][2] = $this->make_post(2, 'B');

		$result = Tsubakuro_Evaluations::get_evaluations(array('include_ids' => array(2)));

		$this->assertCount(1, $result);
		$this->assertSame(2, $result[0]['id']);
	}

	public function test_get_evaluations_returns_empty_for_empty_include_ids(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][1] = $this->make_post(1, 'A');

		$result = Tsubakuro_Evaluations::get_evaluations(array('include_ids' => array()));

		$this->assertSame(array(), $result);
	}

	public function test_evaluation_list_args_from_request_accepts_dates_and_related_insight(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][50] = $this->make_insight_post(50);
		Tsubakuro_Insights::save_meta(50, array('evaluations' => array(1, 2)));
		$_GET = array(
			'target_post'    => '9',
			'implemented_at' => '2026-05-01',
			'due_at'         => '2026-05-15',
			'insight'        => '50',
		);

		$args = Tsubakuro_Evaluations_Admin::get_evaluation_list_args_from_request();

		$this->assertSame(9, $args['target_post']);
		$this->assertSame('2026-05-01', $args['implemented_at']);
		$this->assertSame('2026-05-15', $args['due_at']);
		$this->assertSame(50, $args['insight']);
		$this->assertSame(array(1, 2), $args['include_ids']);
	}

	public function test_evaluation_list_args_from_request_drops_invalid_dates(): void
	{
		$_GET = array(
			'implemented_at' => '2026-99-01',
			'due_at'         => 'not-a-date',
		);

		$args = Tsubakuro_Evaluations_Admin::get_evaluation_list_args_from_request();

		$this->assertArrayNotHasKey('implemented_at', $args);
		$this->assertArrayNotHasKey('due_at', $args);
	}
}
