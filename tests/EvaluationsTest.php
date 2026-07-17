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
	}

	private function make_post(int $id, string $title = 'Eval', string $content = '', string $type = 'tsubakuro_evaluation'): object
	{
		return (object) array(
			'ID'            => $id,
			'post_type'     => $type,
			'post_title'    => $title,
			'post_content'  => $content,
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
}
