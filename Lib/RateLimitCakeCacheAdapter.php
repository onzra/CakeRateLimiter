<?php

/**
 * Adapter for CakePHP Cache to implement methods required by RateLimiterComponent
 */
class RateLimitCakeCacheAdapter {

	protected $cacheConfig;

	/**
	 * @param string $config CakePHP cache config name
	 */
	public function __construct($config) {
		$this->cacheConfig = $config;
	}

	/**
	 * @param string $key
	 * @return int
	 */
	public function get($key) {
		$this->setCacheDuration($key);
		return Cache::read($key, $this->cacheConfig);
	}

	/**
	 * @param string $key
	 * @param int $value
	 * @param int $duration
	 * @return bool
	 */
	public function set($key, $value, $duration) {
		//Save duration since Cache has no way of setting during per item
		Cache::set('duration', $duration, $this->cacheConfig);
		Cache::write($key . '-duration', $duration, $this->cacheConfig);

		Cache::set('duration', $duration, $this->cacheConfig);
		return Cache::write($key, $value, $this->cacheConfig);
	}

	/**
	 * @param string $key
	 */
	public function increment($key) {
		$this->setCacheDuration($key);
		Cache::increment($key, 1, $this->cacheConfig);
	}

	/**
	 * Sets the cache duration to the duration stored in the cache for the given key
	 * @param string $key
	 */
	protected function setCacheDuration($key) {
		if($duration = Cache::read($key . '-duration', $this->cacheConfig)) {
			Cache::set('duration', $duration, $this->cacheConfig);
		}
	}
}