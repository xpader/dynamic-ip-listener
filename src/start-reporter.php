<?php
/**
 * Created by PhpStorm.
 * User: pader
 * Date: 2019/12/14
 * Time: 20:28
 */

use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/ip-monitor.php';

/**
 * IP 上报端
 *
 * 连接至中央服务器，并上报IP，当网络断开后重新连接并上报IP
 */
$reporter = new Worker();

$app = IPMonitor::getInstance();

$reporter->onWorkerStart = function($worker) use ($app) {
	$connection = new AsyncTcpConnection('text://'.$app->config['server']);

	$connection->onConnect = function($_) use ($app, $connection) {
		//标记自己是个上报者
		$connection->send($app->pack('identify listener'));

		//连接成功后立即上报一次 IP
		$connection->send($app->pack('reip'));
	};

	$connection->onMessage = function($_, $message) use ($app, $connection) {
		$cmd = strtok($message, ' ');

		switch ($cmd) {
			//收到 Ping 后回应 Pong
			case 'ping':
				$connection->send($app->pack('pong'));
				break;

			//得到IP操作
			case 'reip':
				$ip = strtok(' ');
				if ($ip === $app->currentIp) {
					return;
				}

				echo "Got new ip: $ip.\r\n";
				break;

			default:
				echo "Unknow command: $cmd.\r\n";
		}
	};

	$connection->onClose = function() use ($connection) {
		echo "Server closed, will reconnect.\r\n";
		//5秒后尝试重连
		$connection->reconnect(5);
	};

	$connection->onError = function($_, $code, $msg) {
		echo "Error: $code $msg\r\n";
	};

	$connection->connect();
};

Worker::runAll();
