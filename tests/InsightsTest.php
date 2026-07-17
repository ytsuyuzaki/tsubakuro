<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Tsubakuro_Insights data layer.
 */
class InsightsTest extends TestCase
{

	protected function setUp(): void
	{
		tsubakuro_test_reset();
	}

	private function make_post(int $id, string $title = 'Insight', string $type = 'tsubakuro_insight'): object
	{
		return (object) array(
			'ID'            => $id,
			'post_type'     => $type,
			'post_title'    => $title,
			'post_content'  => '',
			'post_date'     => '2026-05-01 10:00:00',
			'post_modified' => '2026-05-03 11:00:00',
			'post_author'   => 3,
		);
	}

	public function test_get_insight_returns_null_for_wrong_type(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][10] = $this->make_post(10, 'x', 'tsubakuro_task');
		$this->assertNull(Tsubakuro_Insights::get_insight(10));
	}

	public function test_save_meta_persists_fields_and_computes_success_rate(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][5] = $this->make_post(5, '比較表の知見');

		Tsubakuro_Insights::save_meta(5, array(
			'site'          => 'example.com',
			'post_kind'     => '比較記事',
			'hypothesis'    => '比較表を追加すると検索順位が上がる',
			'conclusion'    => '比較系の記事では比較表を標準化する',
			'total_count'   => 5,
			'success_count' => 4,
			'status'        => 'effective',
			'action'        => 'standardize',
			'evaluations'   => array(11, 12, 13),
		));

		$insight = Tsubakuro_Insights::get_insight(5);

		$this->assertSame('example.com', $insight['site']);
		$this->assertSame('比較記事', $insight['post_kind']);
		$this->assertSame(5, $insight['total_count']);
		$this->assertSame(4, $insight['success_count']);
		$this->assertSame(80.0, $insight['success_rate']);
		$this->assertSame('effective', $insight['status']);
		$this->assertSame('有効', $insight['status_label']);
		$this->assertSame('standardize', $insight['action']);
		$this->assertSame('標準施策として採用する', $insight['action_label']);
		$this->assertSame(array(11, 12, 13), $insight['evaluation_ids']);
	}

	public function test_success_rate_null_when_no_total(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][6] = $this->make_post(6);
		Tsubakuro_Insights::save_meta(6, array('success_count' => 2));

		$insight = Tsubakuro_Insights::get_insight(6);
		$this->assertNull($insight['success_rate']);
	}

	public function test_save_meta_rejects_unknown_status_and_action(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][7] = $this->make_post(7);
		Tsubakuro_Insights::save_meta(7, array('status' => 'bogus', 'action' => 'bogus'));

		$insight = Tsubakuro_Insights::get_insight(7);
		$this->assertSame('', $insight['status']);
		$this->assertSame('', $insight['action']);
	}

	public function test_empty_status_clears_previously_set_value(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][9] = $this->make_post(9);
		Tsubakuro_Insights::save_meta(9, array('status' => 'effective'));
		$this->assertSame('effective', Tsubakuro_Insights::get_insight(9)['status']);

		Tsubakuro_Insights::save_meta(9, array('status' => ''));
		$this->assertSame('', Tsubakuro_Insights::get_insight(9)['status']);
	}

	public function test_save_linked_evaluations_replaces_previous_set(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][8] = $this->make_post(8);
		Tsubakuro_Insights::save_meta(8, array('evaluations' => array(1, 2, 3)));
		Tsubakuro_Insights::save_meta(8, array('evaluations' => array(4, 5)));

		$insight = Tsubakuro_Insights::get_insight(8);
		$this->assertSame(array(4, 5), $insight['evaluation_ids']);
	}

	public function test_get_insights_for_evaluation_reverse_lookup(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][20] = $this->make_post(20, 'Insight A');
		$GLOBALS['tsubakuro_test']['posts'][21] = $this->make_post(21, 'Insight B');
		Tsubakuro_Insights::save_meta(20, array('evaluations' => array(100, 200)));
		Tsubakuro_Insights::save_meta(21, array('evaluations' => array(300)));

		$result = Tsubakuro_Insights::get_insights_for_evaluation(100);

		$this->assertCount(1, $result);
		$this->assertSame(20, $result[0]['id']);
	}

	public function test_get_insights_filters_by_status(): void
	{
		$GLOBALS['tsubakuro_test']['posts'][30] = $this->make_post(30, 'A');
		$GLOBALS['tsubakuro_test']['posts'][31] = $this->make_post(31, 'B');
		Tsubakuro_Insights::save_meta(30, array('status' => 'effective'));
		Tsubakuro_Insights::save_meta(31, array('status' => 'hypothesis'));

		$result = Tsubakuro_Insights::get_insights(array('status' => 'effective'));

		$this->assertCount(1, $result);
		$this->assertSame(30, $result[0]['id']);
	}
}
