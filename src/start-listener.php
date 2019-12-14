<?php
/**
 * Created by PhpStorm.
 * User: pader
 * Date: 2019/12/14
 * Time: 20:30
 */

use Workerman\Worker;
use Workerman\Connection\AsyncTcpConnection;

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/ip-monitor.php';

/**
 * IP 监听端
 *
 * 连接至中央服务器，并获取并监听IP变化，一旦IP发生变化，将调用并传入IP到相应逻辑
 */
$listener = new Worker();

$app = IPMonitor::getInstance();
$procs = [];

$listener->onWorkerStart = function($worker) use ($app) {
	$connection = new AsyncTcpConnection('text://'.$app->config['server']);

	$connection->onConnect = function($_) use ($app, $connection) {
		//标记自己是个监听者
		$connection->send($app->pack('identify listener'));

		//连接成功后立即获取一次 IP
		$connection->send($app->pack('getip'));
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

				echo "Got new ip: $ip, start processing..\r\n";
				processIpChanged($ip);
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

$listener->onWorkerStop = function() {
	//进程结束时关闭所有启动的通道进程
	killTunnels();
};

function killTunnels() {
	global $procs;

	//停止原先的 ss-tunnel 进程
	while ($proc = array_pop($procs)) {
		$status = proc_get_status($proc);
		$pid = $status['pid'];

		echo "Kill process $pid.\r\n";

		if ($status['running']) {
			proc_terminate($proc);
			proc_close($proc);
		}
	}
}

/**
 * 处理IP变化事件
 *
 * @param string $ip
 */
function processIpChanged($ip) {
	global $app, $procs;

	killTunnels();

	$ss = $app->config['tunnel_server'];

	//写入新配置
	$confFile = __DIR__.'/ss-conf.json';
	file_put_contents($confFile, json_encode([
		'server' => $ip,
		'server_port' => $ss['port'],
		'password' => $ss['password'],
		'method' => $ss['method']
	]));

	//重启启动这些新的进程

	foreach ($app->config['tunnel_list'] as $row) {
		list($localPort, $remoteHost, $remotePort) = explode(':', $row);
		$cmd = "{$ss['bin']} -l $localPort -L $remoteHost:$remotePort -c $confFile";
		$logFile = $app->config['log_dir'].'/local-'.$localPort.'.log';

		//$pid = exec($cmd." > $logFile 2>&1 & echo $!");

		$descriptorspec = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			//2 => ['pipe', 'w']
			2 => ['file', $logFile, 'a']
		];
		$proc = proc_open($cmd, $descriptorspec, $pipes);
		$status = proc_get_status($proc);
		$pid = $status['pid'];
		$procs[] = $proc;

		echo "Started process at pid $pid.\r\n";
	}
}

Worker::runAll();
