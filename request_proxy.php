<?php

require_once 'socket.php';

$manager_connection = new IpcSocketConnection(__DIR__ . '/../data/ipc/request_manager.sock');
$ipc_path = $manager_connection->getMessage();
unset($manager_connection);

$pool_process_connection = new IpcSocketConnection($ipc_path);
$pool_process_connection->sendMessage(
    serialize(array(
        '_SERVER'  => $_SERVER,
        '_GET'     => $_GET,
        '_POST'    => $_POST,
        '_FILES'   => $_FILES,
        '_REQUEST' => $_REQUEST,
        '_SESSION' => $_SESSION,
        '_ENV'     => $_ENV,
        '_COOKIE'  => $_COOKIE,
    ))
);

$headers = unserialize($pool_process_connection->getMessage());
foreach ($headers as $header_entry) {
    header($header_entry);
}

$http_body = $pool_process_connection->getMessage();
echo $http_body;
