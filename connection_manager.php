<?php

require_once 'socket.php';

$pool_listener_address = __DIR__ . '/../data/ipc/mvc_manager.sock';
$pool_listener   = new IpcSocketServer($pool_listener_address);
chmod($pool_listener_address, 0777);

$client_listener_address = __DIR__ . '/../data/ipc/request_manager.sock';
$client_listener = new IpcSocketServer($client_listener_address);
chmod($client_listener_address, 0777);

while (true) {
    // get connection from the mvc pool
    $pool_connection = $pool_listener->acceptConnection();
    // get ipc communication path from the pool process
    $ipc_path = $pool_connection->getMessage();
    // close connection
    unset($pool_connection);

    printf("Pool process is available and wait for connections at '%s'\n", $ipc_path);

    // wait for client connection
    $client_connection = $client_listener->acceptConnection();
    // send just recieved path to the client
    $client_connection->sendMessage($ipc_path);
    // close connection
    unset($client_connection);

    printf("Client request is received\n");
}
