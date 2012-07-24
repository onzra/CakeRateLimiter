<?php

App::uses('RateLimitExceededException', 'RateLimiter.Lib/Error/Exception');

/**
 * Exception raised when a user's total rate limit has been exceeded
 */
class TotalRateLimitExceededException extends RateLimitExceededException {

	public $message = 'Exceeded total request limit';

}