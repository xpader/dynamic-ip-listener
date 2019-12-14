<?php
/**
 * Created by PhpStorm.
 * User: pader
 * Date: 2019/12/14
 * Time: 20:45
 */

class IPMonitor {

	const IDENTIFY_REPORTER = 0;
	const IDENTIFY_LISTENER = 1;

	private static $instance;

	public $config;
	public $currentIp;

	public static function init() {
		self::getInstance();
	}

	public static function getInstance() {
		if (self::$instance === null) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	public function __construct() {
		$this->config = require __DIR__.'/config.php';
	}

	/**
	 * 生成签名后的数据
	 *
	 * @param string $data
	 * @param string $nonce
	 * @param string $timestamp
	 * @return array
	 */
	public function sign($data, $nonce=null, $timestamp=null) {
		$nonce = $nonce ?: uniqid();
		$timestamp = $timestamp ?: time();
		$raw = $nonce.' '.$timestamp.' '.$data;
		$sign = substr(md5($raw.$this->config['token']), 8, 16);
		return compact('sign', 'raw');
	}

	public function pack($data) {
		$sd = $this->sign($data);
		return $sd['sign'].' '.$sd['raw'];
	}

	/**
	 * 验证来访数据是否合法
	 *
	 * @param string $message
	 * @return string|bool 验证成功返回数据，验证失败返回 false
	 */
	public function verify($message) {
		$sign = strtok($message, ' ');
		$nonce = strtok(' ');
		$timestamp = strtok(' ');
		$data = strtok('');

		$rsign = $this->sign($data, $nonce, $timestamp);

		if ($sign !== $rsign['sign']) {
			return false;
		}

		return $data;
	}

}

IPMonitor::init();
