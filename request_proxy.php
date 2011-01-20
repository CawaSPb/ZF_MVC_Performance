<?php

require_once 'socket.php';

$manager_connection = new IpcSocketConnection(__DIR__ . '/../data/ipc/request_manager.sock');
$ipc_path = $manager_connection->getMessage();
unset($manager_connection);

$pool_process_connection = new IpcSocketConnection($ipc_path);
$pool_process_connection->sendMessage(serialize($GLOBALS));

$headers = unserialize($pool_process_connection->getMessage());
foreach ($headers as $header_entry) {
    header($header_entry);
}

$http_body = $pool_process_connection->getMessage();
echo $http_body;
