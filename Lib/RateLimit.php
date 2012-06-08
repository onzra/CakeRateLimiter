<?php

/**
 * RateLimit
 * Specifies a maximum number of requests over a given interval
 */
class RateLimit {

	/* @var int $limit Maximum number of requests allowed in interval */
	public $limit;
	/* @var string $interval Time interval to limit over */
	public $interval;

	const INTERVAL_MINUTE = 'minute';
	const INTERVAL_HOUR = 'hour';
	const INTERVAL_DAY = 'day';

	/**
	 * @param int $limit
	 * @param string $interval
	 * @throws RateLimitConfigurationException
	 */
	public function __construct($limit, $interval) {
		if(!is_numeric($limit)) {
			throw new RateLimitConfigurationException('Invalid rate limit');
		}
		$this->limit = $limit;
		if(!$this->isValidInterval($interval)) {
			throw new RateLimitConfigurationException('Invalid rate limit interval');
		}
		$this->interval = $interval;
	}

	/**
	 * @param string $interval
	 * @return bool
	 */
	protected function isValidInterval($interval) {
		if($interval == self::INTERVAL_MINUTE ||
				$interval == self::INTERVAL_HOUR ||
				$interval == self::INTERVAL_DAY
		) {
			return true;
		}
		return false;
	}

	/**
	 * @return string Date key
	 * @throws RateLimitConfigurationException
	 */
	public function getIntervalKey() {
		switch($this->interval) {
			case self::INTERVAL_MINUTE:
				return date('ymdHi');
			case self::INTERVAL_HOUR:
				return date('ymdH');
			case self::INTERVAL_DAY:
				return date('ymd');
			default:
				throw new RateLimitConfigurationException('Invalid rate limit interval');
		}
	}

	/**
	 * @return int Expiration time of requests in seconds
	 * @throws RateLimitConfigurationException
	 */
	public function getExpiration() {
		switch($this->interval) {
			case self::INTERVAL_MINUTE:
				return 60; //1 minute
			case self::INTERVAL_HOUR:
				return 3600; //1 hour
			case self::INTERVAL_DAY:
				return 86400; //1 day
			default:
				throw new RateLimitConfigurationException('Invalid rate limit interval');
		}
	}
}

class RateLimitConfigurationException extends Exception {}