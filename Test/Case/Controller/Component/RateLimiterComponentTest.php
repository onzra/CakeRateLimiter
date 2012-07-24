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

App::uses('Controller', 'Controller');
App::uses('Router', 'Routing');
App::uses('RateLimiterComponent', 'RateLimiter.Controller/Component');

/**
 * @property RateLimiterTestController $Controller
 */
class RateLimiterComponentTest extends CakeTestCase {

	protected $getUserResult = '127.0.0.1';

	public function setUp() {
		parent::setUp();

		$this->getUserResult = '127.0.0.1';

		//Configuration
		Configure::write('RateLimiter.totalLimit', array(5, 'day'));
		Configure::write('RateLimiter.methodLimits', array(
				'rate_limiter_test.method_a' => array(2, 'day'),
				'rate_limiter_test.method_b' => array(3, 'day'),
				'rate_limiter_test.method_d' => array(2, 'minute'),
				'rate_limiter_test.method_e' => array(2, 'hour')
			));

		RateLimiterTestStorage::clear();

		$this->initRequest();
	}

	public function tearDown() {
		parent::tearDown();

		unset($this->Controller, $this->Component);
	}

	/**
	 * Test the automatic request logging
	 */
	public function testRequestLogging() {
		//Fake request for method_a
		$this->fakeRequest('rate_limiter_test', 'method_a');

		//Check total
		$key = 'ratelimit_127.0.0.1_' . date('ymd');
		$total_actual = RateLimiterTestStorage::$store[$key];
		$total_expected = 1;
		$this->assertEqual($total_expected, $total_actual);
		//Check method_a
		$key .= '_rate_limiter_test.method_a';
		$method_actual = RateLimiterTestStorage::$store[$key];
		$method_expected = 1;
		$this->assertEqual($method_expected, $method_actual);

		//Fake request for method_b
		$this->fakeRequest('rate_limiter_test', 'method_b');

		//Check total
		$key = 'ratelimit_127.0.0.1_' . date('ymd');
		$total_actual = RateLimiterTestStorage::$store[$key];
		$total_expected = 2;
		$this->assertEqual($total_expected, $total_actual);
		//Check method_b
		$key .= '_rate_limiter_test.method_b';
		$method_actual = RateLimiterTestStorage::$store[$key];
		$method_expected = 1;
		$this->assertEqual($method_expected, $method_actual);
	}

	/**
	 * Test exception is raised when over total limit
	 */
	public function testOverTotalLimit() {
		$this->fakeRequest('rate_limiter_test', 'method_c');
		$this->fakeRequest('rate_limiter_test', 'method_c');
		$this->fakeRequest('rate_limiter_test', 'method_c');
		$this->fakeRequest('rate_limiter_test', 'method_c');
		$this->fakeRequest('rate_limiter_test', 'method_c');
		try {
			$this->fakeRequest('rate_limiter_test', 'method_c');
		} catch(TotalRateLimitExceededException $e) {
			$this->assertEqual($e->getMessage(), 'Exceeded total request limit');
		}
	}

	/**
	 * Test exception is raised when over method limit
	 */
	public function testOverMethodLimit() {
		$this->fakeRequest('rate_limiter_test', 'method_a');
		$this->fakeRequest('rate_limiter_test', 'method_a');
		try {
			$this->fakeRequest('rate_limiter_test', 'method_a');
		} catch(MethodRateLimitExceededException $e) {
			$this->assertEqual($e->getMessage(), 'Exceeded request limit for method rate_limiter_test.method_a');
		}
	}

	/**
	 * Test that exception is raised if over total, but under method limit
	 */
	public function testOverTotalLimitUnderMethodLimit() {
		$this->fakeRequest('rate_limiter_test', 'method_c');
		$this->fakeRequest('rate_limiter_test', 'method_c');
		$this->fakeRequest('rate_limiter_test', 'method_c');
		$this->fakeRequest('rate_limiter_test', 'method_c');
		$this->fakeRequest('rate_limiter_test', 'method_a');
		$this->expectException('TotalRateLimitExceededException');
		$this->fakeRequest('rate_limiter_test', 'method_a');
	}

	/**
	 * Test that method limits only affect the method they are set for
	 */
	public function testMethodLimitOnlyAffectsSpecifiedMethod() {
		$this->fakeRequest('rate_limiter_test', 'method_a');
		$this->fakeRequest('rate_limiter_test', 'method_a');
		try {
			//Exceed method_a limit
			$this->fakeRequest('rate_limiter_test', 'method_a');
		} catch(MethodRateLimitExceededException $e) {
			//ignore
		}
		$this->fakeRequest('rate_limiter_test', 'method_b');
		$this->fakeRequest('rate_limiter_test', 'method_b');
		$this->fakeRequest('rate_limiter_test', 'method_b');
	}

	/**
	 * Test that allowed methods do not get logged
	 */
	public function testAllowedMethodRequestIsNotLogged() {
		$this->fakeRequest('rate_limiter_test', 'method_a', 'method_a');

		//Check total
		$key = 'ratelimit_127.0.0.1_' . date('ymd');
		$total_actual = RateLimiterTestStorage::get($key);
		$total_expected = false;
		$this->assertEqual($total_expected, $total_actual);
		//Check method_a
		$key .= '_rate_limiter_test.method_a';
		$method_actual = RateLimiterTestStorage::get($key);
		$method_expected = false;
		$this->assertEqual($method_expected, $method_actual);
	}

	/**
	 * Test that allowed methods are not limited
	 */
	public function testAllowedMethodNotLimited() {
		//Exceed method and total limits
		$this->fakeRequest('rate_limiter_test', 'method_a', 'method_a');
		$this->fakeRequest('rate_limiter_test', 'method_a', 'method_a');
		$this->fakeRequest('rate_limiter_test', 'method_a', 'method_a');
		$this->fakeRequest('rate_limiter_test', 'method_a', 'method_a');
		$this->fakeRequest('rate_limiter_test', 'method_a', 'method_a');
		$this->fakeRequest('rate_limiter_test', 'method_a', 'method_a');
	}

	/**
	 * Test that disable disables the rate limiter
	 */
	public function testDisable() {
		Configure::write('RateLimiter.disable', true);
		//Re-init component
		$this->initRequest();

		//Exceed method and total limits
		$this->fakeRequest('rate_limiter_test', 'method_a');
		$this->fakeRequest('rate_limiter_test', 'method_a');
		$this->fakeRequest('rate_limiter_test', 'method_a');
		$this->fakeRequest('rate_limiter_test', 'method_a');
		$this->fakeRequest('rate_limiter_test', 'method_a');
		$this->fakeRequest('rate_limiter_test', 'method_a');

		//Check total
		$key = 'ratelimit_127.0.0.1_' . date('ymd');
		$total_actual = RateLimiterTestStorage::get($key);
		$total_expected = false;
		$this->assertEqual($total_expected, $total_actual);

		//Check method_a
		$key .= '_rate_limiter_test.method_a';
		$method_actual = RateLimiterTestStorage::get($key);
		$method_expected = false;
		$this->assertEqual($method_expected, $method_actual);
		Configure::write('RateLimiter.disable', false);
	}

	/**
	 * Tests that each user has separate rate limits
	 */
	public function testUserLimitSeparation() {
		//Test method limit
		$this->fakeRequest('rate_limiter_test', 'method_a');
		$this->fakeRequest('rate_limiter_test', 'method_a');
		//By default RateLimiter uses IP address as user identifier
		$this->getUserResult = '127.0.0.2';
		$this->fakeRequest('rate_limiter_test', 'method_a');

		//Test total
		$this->fakeRequest('rate_limiter_test', 'method_c');
		$this->fakeRequest('rate_limiter_test', 'method_c');
		$this->fakeRequest('rate_limiter_test', 'method_c');
		$this->fakeRequest('rate_limiter_test', 'method_c');
	}

	/**
	 * Tests that if the get user callback does not return a user identifier, an exception is raised
	 */
	public function testGetUserFailureRaisesException() {
		$this->getUserResult = false;
		$this->expectException('RateLimitUserException');
		$this->fakeRequest('rate_limiter_test', 'method_a');
	}

	/**
	 * Tests that an uncallable get user callback will raise an exception
	 */
	public function testUncallableGetUserCallbackRaisesException() {
		$this->expectException('RateLimitUserException');
		$this->Controller->RateLimiter->setGetUserCallback('asdf');
	}

	/**
	 * Tests that an exception is raised if storage engine does not support the necessary methods
	 */
	public function testSettingInvalidStorageEngineRaisesException() {
		$this->expectException('RateLimitStorageException');
		$this->Controller->RateLimiter->setStorageEngine('asdf');
	}

	/**
	 * Test that request logs expire after set interval
	 */
	public function testLoggedRequestsExpire() {
		$this->fakeRequest('rate_limiter_test', 'method_a');
		$this->fakeRequest('rate_limiter_test', 'method_a');
		//Move forward one day
		RateLimiterTestStorage::moveTimeForward(60 * 60 * 24 + 1);
		$this->fakeRequest('rate_limiter_test', 'method_a');
	}

	/**
	 * Test that minute, hour, day intervals expire correctly
	 */
	public function testExpirationOfIntervals() {
		//Minute
		$this->fakeRequest('rate_limiter_test', 'method_d');
		$this->fakeRequest('rate_limiter_test', 'method_d');
		RateLimiterTestStorage::moveTimeForward(60 + 1);
		$this->fakeRequest('rate_limiter_test', 'method_d');
		RateLimiterTestStorage::clear();

		//Hour
		$this->fakeRequest('rate_limiter_test', 'method_e');
		$this->fakeRequest('rate_limiter_test', 'method_e');
		RateLimiterTestStorage::moveTimeForward(60 * 60 + 1);
		$this->fakeRequest('rate_limiter_test', 'method_e');
		RateLimiterTestStorage::clear();

		//Day
		$this->fakeRequest('rate_limiter_test', 'method_a');
		$this->fakeRequest('rate_limiter_test', 'method_a');
		RateLimiterTestStorage::moveTimeForward(60 * 60 * 24 + 1);
		$this->fakeRequest('rate_limiter_test', 'method_a');
	}

	/**
	 * Helper method to bootstrap a CakePHP request
	 */
	public function initRequest() {
		//Initialize fake request / controller
		$request = new CakeRequest(null, false);
		$this->Controller = new RateLimiterTestController($request, $this->getMock('CakeResponse'));

		$collection = new ComponentCollection();
		$collection->init($this->Controller);
		$this->Component = new RateLimiterComponent($collection);
		$this->Component->request = $request;
		$this->Component->response = $this->getMock('CakeResponse');

		$this->Controller->Components->init($this->Controller);
	}

	/**
	 * Performs a fake CakePHP request with respect to the rate limiter component
	 * @param string $controller Controller of request
	 * @param string $action Action of request
	 * @param bool $allow True to allow the request method in beforeFilter
	 * @param bool $useFakeStorage False to stop using RateLimiterTestStorage as storage engine
	 */
	public function fakeRequest($controller, $action, $allow=false, $useFakeStorage=true) {
		$this->Controller->RateLimiter->initialize($this->Controller);
		$this->Controller->request->addParams(array(
				'controller' => $controller,
				'action' => $action
			)
		);
		//Controller's beforeFilter
		if($useFakeStorage) {
			$this->Controller->RateLimiter->setStorageEngine(new RateLimiterTestStorage());
		}
		if($allow) {
			$this->Controller->RateLimiter->allow($allow);
		}

		//Mock get user callback
		$this->Controller->RateLimiter->setGetUserCallback(array($this, 'getUserCallback'));

		$this->Controller->RateLimiter->startup($this->Controller);
	}

	/**
	 * @return string
	 */
	public function getUserCallback() {
		return $this->getUserResult;
	}
}
/**
 * Mock controller for testing
 */
class RateLimiterTestController extends Controller {

	public $name = 'RateLimiterTest';

	public $components = array('RateLimiter.RateLimiter' => array(
		'defaultCacheName' => 'rate_limiter_test'
	));

	public function __construct($request, $response) {
		$request->addParams(Router::parse('/auth_test'));
		$request->here = '/auth_test';
		$request->webroot = '/';
		Router::setRequestInfo($request);
		parent::__construct($request, $response);
	}

	public function method_a() {
	}

	public function method_b() {
	}

	public function method_c() {
	}

	public function method_d() {
	}

	public function method_e() {
	}

}
/**
 * Mock storage engine for testing
 */
class RateLimiterTestStorage {

	public static $store = array();

	public static $expirations = array();

	protected static $addedTime = 0;

	public function get($key) {
		if(isset(self::$expirations[$key]) && self::$expirations[$key] >= self::getCurrentTime()) {
			if(isset(self::$store[$key])) {
				return self::$store[$key];
			}
		}
		return false;
	}

	public function set($key, $value, $expiration) {
		self::$store[$key] = $value;
		self::$expirations[$key] = self::getCurrentTime() + $expiration;
	}

	public function increment($key) {
		if(isset(self::$expirations[$key]) && self::$expirations[$key] >= self::getCurrentTime()) {
			if(isset(self::$store[$key])) {
				self::$store[$key]++;
			}
		}
	}

	public static function clear() {
		self::$store = array();
		self::resetTime();
	}

	public static function moveTimeForward($seconds) {
		self::$addedTime += $seconds;
	}

	public static function resetTime() {
		self::$addedTime = 0;
	}

	protected static function getCurrentTime() {
		return time() + self::$addedTime;
	}

}
