<?php
/**
 * Intelephense-only symbols for WordPress integration tests.
 *
 * The real WP_UnitTestCase class is provided by the WordPress test suite when
 * integration tests run inside wp-env. This file is not loaded by PHPUnit; it
 * only gives IDE static analysis enough context for local editing.
 *
 * @package Tsubakuro
 */

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
	/**
	 * Minimal IDE stub for WordPress' integration test base class.
	 *
	 * @method void assertArrayHasKey($key, $array, string $message = '')
	 * @method void assertContains($needle, iterable $haystack, string $message = '')
	 * @method void assertCount(int $expectedCount, $haystack, string $message = '')
	 * @method void assertFalse($condition, string $message = '')
	 * @method void assertIsArray($actual, string $message = '')
	 * @method void assertIsInt($actual, string $message = '')
	 * @method void assertSame($expected, $actual, string $message = '')
	 * @method void assertTrue($condition, string $message = '')
	 */
	abstract class WP_UnitTestCase {}
}
