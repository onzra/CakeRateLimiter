<?php

/**
 * Test suite to run all tests in the plugin
 */
class AllTest extends CakeTestSuite {

	public static function suite() {
		$suite = new CakeTestSuite('All RateLimiter tests');
		$suite->addTestDirectoryRecursive(APP . 'Plugin' . DS . 'RateLimiter' . DS . 'Test' . DS . 'Case');
		return $suite;
	}

}