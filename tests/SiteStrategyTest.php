<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Tsubakuro_Site_Strategy data layer (options-backed singleton).
 */
class SiteStrategyTest extends TestCase
{

	protected function setUp(): void
	{
		tsubakuro_test_reset();
	}

	public function test_get_strategy_returns_empty_defaults_when_unset(): void
	{
		$strategy = Tsubakuro_Site_Strategy::get_strategy();

		foreach (array_keys(Tsubakuro_Site_Strategy::FIELDS) as $field) {
			$this->assertSame('', $strategy[$field]);
		}
		$this->assertSame('', $strategy['updated_at']);
		$this->assertSame(0, $strategy['updated_by']);
	}

	public function test_save_strategy_persists_all_fields_and_metadata(): void
	{
		$saved = Tsubakuro_Site_Strategy::save_strategy(array(
			'purpose'   => '初めての人でも失敗しない選び方を提供する',
			'position'  => '比較分野で最初に思い出されるメディア',
			'direction' => '一次情報にもとづく比較を増やす',
			'audience'  => '初めて検討する20〜30代',
			'value'     => '中立的な比較と根拠の明示',
		));

		$this->assertSame('初めての人でも失敗しない選び方を提供する', $saved['purpose']);
		$this->assertSame('比較分野で最初に思い出されるメディア', $saved['position']);
		$this->assertSame('一次情報にもとづく比較を増やす', $saved['direction']);
		$this->assertSame('初めて検討する20〜30代', $saved['audience']);
		$this->assertSame('中立的な比較と根拠の明示', $saved['value']);

		// Maintenance keys are stamped from the WordPress stubs.
		$this->assertSame('2026-05-02 00:00:00', $saved['updated_at']);
		$this->assertSame(7, $saved['updated_by']);

		// Round-trips through the option store.
		$reloaded = Tsubakuro_Site_Strategy::get_strategy();
		$this->assertSame($saved, $reloaded);
		$this->assertArrayHasKey(Tsubakuro_Site_Strategy::OPTION, $GLOBALS['tsubakuro_test']['options']);
	}

	public function test_save_strategy_updates_only_provided_fields(): void
	{
		Tsubakuro_Site_Strategy::save_strategy(array(
			'purpose'  => '当初の目的',
			'position' => '当初のポジション',
		));

		Tsubakuro_Site_Strategy::save_strategy(array(
			'direction' => '新しい方向性',
		));

		$strategy = Tsubakuro_Site_Strategy::get_strategy();
		$this->assertSame('当初の目的', $strategy['purpose']);
		$this->assertSame('当初のポジション', $strategy['position']);
		$this->assertSame('新しい方向性', $strategy['direction']);
	}

	public function test_save_strategy_sanitizes_values(): void
	{
		$saved = Tsubakuro_Site_Strategy::save_strategy(array(
			'purpose' => "目的<script>alert(1)</script>",
		));

		$this->assertStringNotContainsString('<script>', $saved['purpose']);
	}
}
