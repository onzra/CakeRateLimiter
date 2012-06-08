<?php

App::uses('RateLimitCakeCacheAdapter', 'RateLimiter.Lib');

class RateLimitCakeCacheAdapterTest extends CakeTestCase {

	public function setUp() {
		Cache::config('rate_limit_test', array(
				'engine' => 'Apc',
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

	public function testIncrement() {
		$cache = new RateLimitCakeCacheAdapter('rate_limit_test');
		$cache->set('test_key', 1, 1000);
		$cache->increment('test_key');
		$this->assertEqual($cache->get('test_key'), 2);
	}

	public function testDuration() {
		$cache = new RateLimitCakeCacheAdapter('rate_limit_test');
		$cache->set('test_key', 1, 1);
		sleep(2);
		$this->assertEqual($cache->get('test_key'), false);
	}
}