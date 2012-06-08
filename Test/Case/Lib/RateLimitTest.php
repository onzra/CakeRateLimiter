<?php

App::uses('RateLimit', 'RateLimiter.Lib');

class RateLimitTest extends CakeTestCase {

	public function testConstruct() {
		$limit = new RateLimit(100, RateLimit::INTERVAL_DAY);
		$this->assertEqual($limit->limit, 100);
		$this->assertEqual($limit->interval, RateLimit::INTERVAL_DAY);
	}

	public function testInvalidLimitRaisesException() {
		$this->expectException('RateLimitConfigurationException');
		$limit = new RateLimit('a', RateLimit::INTERVAL_DAY);
	}

	public function testInvalidIntervalRaisesException() {
		$this->expectException('RateLimitConfigurationException');
		$limit = new RateLimit(100, 'a');
	}

	public function testAllIntervalsAreValid() {
		$limit = new RateLimit(100, RateLimit::INTERVAL_DAY);
		$limit = new RateLimit(100, RateLimit::INTERVAL_HOUR);
		$limit = new RateLimit(100, RateLimit::INTERVAL_MINUTE);
	}

	public function testIntervalKeys() {
		$limit = new RateLimit(100, RateLimit::INTERVAL_DAY);
		$this->assertEqual($limit->getIntervalKey(), date('ymd'));
		$limit = new RateLimit(100, RateLimit::INTERVAL_HOUR);
		$this->assertEqual($limit->getIntervalKey(), date('ymdH'));
		$limit = new RateLimit(100, RateLimit::INTERVAL_MINUTE);
		$this->assertEqual($limit->getIntervalKey(), date('ymdHi'));
	}

	public function testIntervalExpirations() {
		$limit = new RateLimit(100, RateLimit::INTERVAL_DAY);
		$this->assertEqual($limit->getExpiration(), 60 * 60 * 24);
		$limit = new RateLimit(100, RateLimit::INTERVAL_HOUR);
		$this->assertEqual($limit->getExpiration(), 60 * 60);
		$limit = new RateLimit(100, RateLimit::INTERVAL_MINUTE);
		$this->assertEqual($limit->getExpiration(), 60);
	}

	public function testGetIntervalKeyRaisesAnExceptionWithInvalidInterval() {
		$limit = new RateLimit(100, RateLimit::INTERVAL_MINUTE);
		$limit->interval = 'a';
		$this->expectException('RateLimitConfigurationException');
		$limit->getIntervalKey();
	}

	public function testGetExpirationRaisesAnExceptionWithInvalidInterval() {
		$limit = new RateLimit(100, RateLimit::INTERVAL_MINUTE);
		$limit->interval = 'a';
		$this->expectException('RateLimitConfigurationException');
		$limit->getExpiration();
	}
}