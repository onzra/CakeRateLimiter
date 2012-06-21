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

App::uses('RateLimit', 'RateLimiter.Lib');
App::uses('RateLimitCakeCacheAdapter', 'RateLimiter.Lib');

/**
 * RateLimiterComponent
 * Provides support for rate limiting by method or total requests.
 * Each rate limit is configured as array(max requests, interval).
 * Valid intervals are 'day', 'hour', 'minute'.
 * By default will use CakePHP file caching. Use setStorageEngine in beforeFilter to use another solution.
 * Configuration
 * 	RateLimiter.totalLimit - Rate limit for maximum total requests per user
 * 	RateLimiter.methodLimits - Array of rate limits for specific methods, key is 'controller.action'
 * 	RateLimiter.disable - Disable rate limiting
 * Settings
 * 	totalLimit - Same as RateLimiter.totalLimit configuration
 * 	methodLimits - Same as RateLimiter.methodLimits
 * 	userCallback - Callback to get user identifier. Function takes one argument, the current CakeRequest object.
 * 	defaultCacheName - Cache config name for default storage engine (CakePHP cache)
 */
class RateLimiterComponent extends Component {

	/* @var RateLimit Total request limit */
	protected $totalLimit;
	/* @var array Rate limits per method */
	protected $methodLimits;
	/* @var bool True if rate limiter was disabled in config */
	protected $disable = false;
	/* @var object Object for storing requests */
	protected $storageEngine;
	/* @var string Name for default CakePHP cache config */
	protected $defaultCacheName = 'rate_limiter_requests';
	/* @var string|array Callback to get user identifier */
	protected $userCallback;
	/* @var array Controller actions which do not use rate limiting */
	protected $allowedActions = array();
	/* @var array Method list for bound controller */
	protected $_methods = array();
	/* @var CakeRequest Current request */
	protected $_request;

	/**
	 * @param ComponentCollection $collection
	 * @param array $settings
	 */
	public function __construct(ComponentCollection $collection, $settings = array()) {
		$this->loadSettingsFromConfig();
		parent::__construct($collection, $settings);
	}

	/**
	 * Load settings from app configuration
	 */
	protected function loadSettingsFromConfig() {
		if($total_limit = Configure::read('RateLimiter.totalLimit')) {
			$this->totalLimit = $this->rateLimitFromConfig($total_limit);
		}
		if($method_limits = Configure::read('RateLimiter.methodLimits')) {
			$this->methodLimits = $this->rateLimitFromConfig($method_limits);
		}
		if(Configure::read('RateLimiter.disable')) {
			$this->disable = true;
		}
	}

	/**
	 * @param array $config Rate limit config, array(limit, interval) or an array of rate limit configs
	 * @return array|RateLimit
	 */
	protected function rateLimitFromConfig($config) {
		if(isset($config[0]) && isset($config[1]) && !is_array($config[0])) {
			return new RateLimit($config[0], $config[1]);
		} else {
			$rate_limits = array();
			foreach($config as $key => $c) {
				if(isset($c[0]) && isset($c[1])) {
					$rate_limits[$key] = new RateLimit($c[0], $c[1]);
				}
			}
			return $rate_limits;
		}
	}

	/**
	 * @param Controller $controller A reference to the instantiating controller object
	 */
	public function initialize(Controller $controller) {
		$this->_request = $controller->request;
		$this->_methods = $controller->methods;
	}

	/**
	 * Takes a list of actions in the current controller for which rate limiting is not used
	 *
	 * You can use allow with either an array, or var args.
	 *
	 * `$this->RateLimiter->allow(array('edit', 'add'));` or
	 * `$this->RateLimiter->allow('edit', 'add');` or
	 * `$this->RateLimiter->allow();` to allow all actions
	 *
	 * @param mixed $action,... Controller action name or array of actions
	 * @return void
	 */
	public function allow($action = null) {
		$args = func_get_args();
		if(empty($args) || $action === null) {
			$this->allowedActions = $this->_methods;
		} else {
			if(isset($args[0]) && is_array($args[0])) {
				$args = $args[0];
			}
			$this->allowedActions = array_merge($this->allowedActions, $args);
		}
	}

	/**
	 * Check if the current request is allowed to skip rate limiting
	 * @return bool True if current request should not be rate limited
	 */
	protected function isAllowed() {
		return $this->allowedActions == array('*') ||
				in_array($this->_request->params['action'], array_map('strtolower', $this->allowedActions));
	}

	/**
	 * Perform the rate limit check and logs the request
	 * @param Controller $controller
	 * @throws RateLimitUserException
	 * @throws TotalRateLimitExceededException
	 * @throws MethodRateLimitExceededException
	 */
	public function startup(Controller $controller) {
		if(!$this->storageEngine) {
			//Load default storage engine
			$this->loadDefaultStorageEngine();
		}
		if(!$this->userCallback) {
			//Use default user callback (IP address)
			$this->userCallback = 'RateLimiterComponent::getUserAsIp';
		}

		if(!$this->disable && !$this->isAllowed()) {
			if($user = $this->getUser()) {
				//Check total request limit
				if($this->isOverTotalLimit($user)) {
					throw new TotalRateLimitExceededException();
				}
				//Check method request limit
				$method = $this->getCurrentMethod();
				if($this->isOverMethodLimit($user, $method)) {
					throw new MethodRateLimitExceededException($method);
				}
				//Record request
				$this->recordRequest($user);
			} else {
				throw new RateLimitUserException('Unable to get user identifier');
			}
		}
	}

	/**
	 * Loads the default storage engine, CakePHP file caching
	 */
	protected function loadDefaultStorageEngine() {
		//Create a cache config
		Cache::config($this->defaultCacheName, array(
				'engine' => 'Apc',
				'path' => CACHE,
				'prefix' => 'rate_limiter'
		));
		$this->storageEngine = new RateLimitCakeCacheAdapter($this->defaultCacheName);
	}

	/**
	 * @return string User identifier
	 * @throws RateLimitUserException
	 */
	protected function getUser() {
		if(is_callable($this->userCallback)) {
			return call_user_func($this->userCallback, $this->_request);
		} else {
			throw new RateLimitUserException('userCallback is not a callable function');
		}
	}

	/**
	 * @param string $user User identifier
	 * @return bool True if user is over total requests limit
	 */
	protected function isOverTotalLimit($user) {
		if($this->totalLimit) {
			$current = $this->getTotalRequests($user);
			if($current >= $this->totalLimit->limit) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $user User identifier
	 * @return int Total requests made by the user
	 */
	protected function getTotalRequests($user) {
		$key = $this->getKey($user, $this->totalLimit->getIntervalKey());
		return (int)$this->storageEngine->get($key);
	}

	/**
	 * @param string $user
	 * @param string $method
	 * @return bool
	 */
	protected function isOverMethodLimit($user, $method) {
		if(isset($this->methodLimits[$method])) {
			$method_requests = $this->getMethodRequests($user, $method);
			if($method_requests >= $this->methodLimits[$method]->limit) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return string Currently request method, controller.action
	 */
	protected function getCurrentMethod() {
		return $this->_request->params['controller'] . '.' . $this->_request->params['action'];
	}

	/**
	 * @param string $user User identifier
	 * @param string $method Method, controller.action
	 * @return int
	 */
	protected function getMethodRequests($user, $method) {
		if(isset($this->methodLimits[$method])) {
			$key = $this->getKey($user, $this->methodLimits[$method]->getIntervalKey(), $method);
			return (int)$this->storageEngine->get($key);
		}
		return 0;
	}

	/**
	 * Records the current request
	 * @param string $user User identifier
	 */
	protected function recordRequest($user) {
		$this->recordTotalRequest($user);
		$this->recordMethodRequest($user, $this->getCurrentMethod());
	}

	/**
	 * Records a request made by the user against their total request count
	 * @param $user User identifier
	 */
	protected function recordTotalRequest($user) {
		if($this->totalLimit) {
			$key = $this->getKey($user, $this->totalLimit->getIntervalKey());
			$this->_recordRequest($key, $this->totalLimit->getExpiration());
		}
	}

	/**
	 * @param string $user User identifier
	 * @param string $method Method, controller.action
	 */
	protected function recordMethodRequest($user, $method) {
		if(isset($this->methodLimits[$method])) {
			$key = $this->getKey($user, $this->methodLimits[$method]->getIntervalKey(), $method);
			$this->_recordRequest($key, $this->methodLimits[$method]->getExpiration());
		}
	}

	/**
	 * @param string $key Request recording key
	 * @param int $expiration Seconds before recorded requests are expired
	 */
	protected function _recordRequest($key, $expiration) {
		$value = $this->storageEngine->get($key);
		if($value === false) {
			$this->storageEngine->set($key, 1, $expiration);
		} else {
			$this->storageEngine->increment($key);
		}
	}

	/**
	 * @param $user User identifier
	 * @param $interval_key Rate limiter date interval key
	 * @param string $method Optional method name if recording request for a method
	 * @return string Key for storing request count in storage engine
	 */
	protected function getKey($user, $interval_key, $method = '') {
		$key = 'ratelimit_' . $user . '_' . $interval_key;
		if($method) {
			$key .= '_' . $method;
		}
		return $key;
	}

	/**
	 * @static
	 * @param CakeRequest $request
	 * @return string
	 */
	public static function getUserAsIp(CakeRequest $request) {
		return $request->clientIp();
	}

	/**
	 * Sets the store engine used to store the request counters. Must support get, set, and increment methods.
	 * @param object $engine
	 * @throws RateLimitStorageException
	 */
	public function setStorageEngine($engine) {
		if(method_exists($engine, 'get') && method_exists($engine, 'set') && method_exists($engine, 'increment')) {
			$this->storageEngine = $engine;
		} else {
			throw new RateLimitStorageException('Invalid storage engine. Must support get, set, and increment methods.');
		}
	}

	/**
	 * @param callable $callback Callback
	 * @throws RateLimitUserException
	 */
	public function setGetUserCallback($callback) {
		if(is_callable($callback)) {
			$this->userCallback = $callback;
		} else {
			throw new RateLimitUserException('Invalid getUser callback');
		}
	}

}

class RateLimitExceededException extends Exception {}
class TotalRateLimitExceededException extends RateLimitExceededException {
	public $message = 'Exceeded total request limit';
}
class MethodRateLimitExceededException extends RateLimitExceededException {
	/**
	 * @param string $method name of method that was exceeded
	 */
	public function __construct($method) {
		parent::__construct('Exceeded request limit for method ' . $method);
	}
}
class RateLimitStorageException extends Exception {}
class RateLimitUserException extends Exception {}