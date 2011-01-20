<?php
require_once 'socket.php';

// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

// Ensure library/ is on include_path
//set_include_path(implode(PATH_SEPARATOR, array(
//    realpath(APPLICATION_PATH . '/../library'),
//    get_include_path(),
//)));
set_include_path(implode(PATH_SEPARATOR,
                         array('../library',
                               get_include_path())
                        )
                 );

/** Zend_Application */
require_once 'Zend/Application/Application.php';


$socket_name = __DIR__ . '/../data/ipc/app1.ipc.' . posix_getpid() . '.sock';
$client_listener = new IpcSocketServer($socket_name);
chmod($socket_name, 0777);

// Create application, bootstrap, and run
$application = new \Zend\Application\Application(
    APPLICATION_ENV,
    APPLICATION_PATH . '/configs/application.ini'
);
$application->bootstrap();


while (true) {
    // Send message to connection manager (inform it, that we are ready for connections)
    $manager_connection = new IpcSocketConnection(__DIR__ . '/../data/ipc/mvc_manager.sock');
    $manager_connection->sendMessage($socket_name);
    unset($manager_connection);

    // wait for client connection
    $client_connection = $client_listener->acceptConnection();

    try {
        $start = microtime(true);

        $global_vars = unserialize($client_connection->getMessage());
        foreach ($global_vars as $var_name => $value) {
            $$var_name = $value;
        }

        ob_start();
        $application->run();
        $output = ob_get_contents()
                . sprintf("<pre>\nOverall execution time:            %.4f\n</pre>\n", microtime(true) - $start);
        ob_end_clean();

        $headers = headers_list();
        header_remove();

        $client_connection->sendMessage(serialize($headers));
        $client_connection->sendMessage($output);
    } catch (Exception $e) {
        $client_connection->sendMessage(serialize(array()));
        $client_connection->sendMessage('<html><body><pre>' . $e->getMessage() . '</pre></body></html>');
    }

    unset($client_connection);
}

