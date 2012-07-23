<?php
/**
 * RateLimiting support for CakePHP
 *
 * ONZRA: Enterprise Development
 * Copyright 2012, ONZRA LLC (http://www.ONZRA.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2012, ONZRA LLC (http://www.ONZRA.com)
 * @link          https://github.com/onzra/CakeRateLimiter CakeRateLimiter
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

App::uses('RateLimitCakeCacheAdapter', 'RateLimiter.Lib');

class RateLimitCakeCacheAdapterTest extends CakeTestCase {

	public function setUp() {
		Cache::config('rate_limit_test', array(
				'engine' => 'File',
				'path' => CACHE,
				'prefix' => 'rate_limit_adapter_test'
		));
	}

	public function tearDown() {
		Cache::clear(false, 'rate_limit_test');
	}

	public function testSet() {
		$cache = new RateLimitCakeCacheAdapter('rate_limit_test');
		$cache->set('test_key', 1, 1000);
		$this->assertEqual(Cache::read('test_key', 'rate_limit_test'), 1);
	}

	public function testGet() {
		$cache = new RateLimitCakeCacheAdapter('rate_limit_test');
		$cache->set('test_key', 1, 1000);
		$this->assertEqual($cache->get('test_key'), 1);
	}

	//Cannot test increment using File engine
//	public function testIncrement() {
//		$cache = new RateLimitCakeCacheAdapter('rate_limit_test');
//		$cache->set('test_key', 1, 1000);
//		$cache->increment('test_key');
//		$this->assertEqual($cache->get('test_key'), 2);
//	}

	public function testDuration() {
		$cache = new RateLimitCakeCacheAdapter('rate_limit_test');
		$cache->set('test_key', 1, 1);
		sleep(2);
		$this->assertEqual($cache->get('test_key'), false);
	}
}