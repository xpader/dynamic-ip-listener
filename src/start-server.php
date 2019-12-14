<?php
/**
 * Created by PhpStorm.
 * User: pader
 * Date: 2019/12/14
 * Time: 20:29
 */

use Workerman\Worker;
use Workerman\Lib\Timer;

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/ip-monitor.php';

/**
 * IP 中央控制端
 *
 * 接收上报端上报的IP，并广播至所有已认证的连接
 */
$server = new Worker('text://0.0.0.0:8100');
$server->name = 'ip-monitor-server';
$server->count = 1;

$app = IPMonitor::getInstance();

$server->onWorkerStart = function($worker) {
	/* @var $worker Worker */

	//每隔10秒向所有已验证的连接发送心跳
	Timer::add(10, function() {
		broadcastConnections('ping '.uniqid());
	});
};

$server->onConnect = function($connection) {
	/* @var $connection \Workerman\Connection\TcpConnection */

	//新连上来的连接如果五秒内没有有效的消息，则链接会被强制关闭
	Timer::add(3, function() use ($connection) {
		if (!isset($connection->identify)) {
			$connection->close("You're not verified.");
		}
	}, [], false);

	echo 'Connection '.$connection->getRemoteAddress()." connected.\r\n";
};

$server->onMessage = function($connection, $message) use ($app) {
	/* @var $connection \Workerman\Connection\TcpConnection */
	$data = $app->verify($message);

	if ($data === false) {
		$connection->close("You're not verified.");
		return;
	}

	if (!isset($connection->identify)) {
		$connection->identify = true;
	}

	$cmd = strtok($data, ' ');

	switch ($cmd) {
		//IP发生更新，通知所有已认证连接
		case 'reip':
			$ip = $connection->getRemoteIp();
			if ($ip != $app->currentIp) {
				$app->currentIp = $ip;
				echo "Received new ip: $ip\r\n";
				broadcastConnections('reip '.$ip);
			}
			break;

		case 'getip':
			$connection->send('reip '.$app->currentIp);
			break;

		case 'pong':
			//echo "Pong ".$connection->getRemoteAddress()."\r\n";
			break;
	}

};

$server->onClose = function($connection) {
	echo 'Connection '.$connection->getRemoteAddress()." closed.\r\n";
};

function broadcastConnections($data) {
	global $server;
	foreach ($server->connections as $connection) {
		if (!isset($connection->identify)) {
			continue;
		}
		$connection->send($data);
	}
}

Worker::runAll();
