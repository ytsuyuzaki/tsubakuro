<?php
/**
 * Intelephense-only symbols for PHPUnit unit tests.
 *
 * PHPUnit is installed in vendor and loaded at test runtime. This file is not
 * loaded by PHPUnit; it only gives IDE static analysis enough context when
 * Intelephense cannot resolve vendor symbols.
 *
 * @package Tsubakuro
 */

namespace PHPUnit\Framework;

if ( ! class_exists( __NAMESPACE__ . '\\TestCase' ) ) {
	/**
	 * Minimal IDE stub for PHPUnit's unit test base class.
	 *
	 * @method void assertArrayHasKey($key, $array, string $message = '')
	 * @method void assertArrayNotHasKey($key, $array, string $message = '')
	 * @method void assertContains($needle, iterable $haystack, string $message = '')
	 * @method void assertCount(int $expectedCount, $haystack, string $message = '')
	 * @method void assertEmpty($actual, string $message = '')
	 * @method void assertFalse($condition, string $message = '')
	 * @method void assertInstanceOf(string $expected, $actual, string $message = '')
	 * @method void assertIsArray($actual, string $message = '')
	 * @method void assertIsInt($actual, string $message = '')
	 * @method void assertNotContains($needle, iterable $haystack, string $message = '')
	 * @method void assertNotEmpty($actual, string $message = '')
	 * @method void assertNull($actual, string $message = '')
	 * @method void assertSame($expected, $actual, string $message = '')
	 * @method void assertStringContainsString(string $needle, string $haystack, string $message = '')
	 * @method void assertTrue($condition, string $message = '')
	 */
	abstract class TestCase {}
}
