<?php

App::uses('RateLimitExceededException', 'RateLimiter.Lib/Error/Exception');

/**
 * Exception raised when a user's method specific rate limit is exceeded
 */
class MethodRateLimitExceededException extends RateLimitExceededException {

	/**
	 * @param string $method name of method that was exceeded
	 */
	public function __construct($method) {
		parent::__construct('Exceeded request limit for method ' . $method);
	}

}