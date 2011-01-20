<?php

abstract class IpcSocket {
    /**
     * Throw socket error exception
     *
     * @param string $message
     * @throws Exception
     */
    public function throwSocketError($message)
    {
        $error_code = socket_last_error();
        throw new Exception(sprintf("%s.\nError code: %d.\nMessage: '%s'.\n",
                                    $message,
                                    $error_code,
                                    socket_strerror($error_code)));
    }
}

class IpcSocketConnection extends IpcSocket {
    /**
     * Socket
     *
     * @var resource
     */
    protected $_socket;

    /**
     * Create connection for data sending and recieving.
     *
     * @param mixed $resource Socket resource or path
     * @throws Exception
     */
    public function __construct($resource)
    {
        if (is_resource($resource)) {
            $this->_socket = $resource;
        } else if (is_string($resource)) {
            $this->_socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
            if ($this->_socket === false) {
                $this->throwSocketError('Socket creation is failed');
            }

            if (!socket_set_block($this->_socket)) {
                $this->throwSocketError('Setting blocking mode is failed');
            }

            if (!socket_connect($this->_socket, $resource)) {
                $this->throwSocketError('Connect operation is failed');
            }
        } else {
            throw new Exception('$resource parameter must be a resource or path to ipc socket.');
        }

        $arrOpt = array('l_onoff' => 1, 'l_linger' => 1);
        socket_set_block($this->_socket);
        socket_set_option($this->_socket, SOL_SOCKET, SO_LINGER, $arrOpt);
    }

    public function __destruct()
    {
        socket_shutdown($this->_socket, 2);
        socket_close($this->_socket);
    }

    /**
     * Read data from the connection
     *
     * @param integer $length number of bytes to read from the connection
     * @return string
     */
    public function read($length = 1)
    {
        $length = socket_recv($this->_socket, $buffer, $length, MSG_WAITALL);
        if ($length === false) {
            $this->throwSocketError('receive operation failed');
        }

        return $buffer;
    }

    /**
     * Read Unsigned Long from the connection
     */
    public function readULong()
    {
        $data = unpack('L' /* unsigned long, 32 bit, machine byte order */, $this->read(4));

        return $data[1];
    }

    /**
     * Get message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->read($this->readULong());
    }

    /**
     * Write data to the connection
     */
    public function write($buffer)
    {
        $length = socket_write($this->_socket, $buffer);
        if ($length === false) {
            $this->throwSocketError('Socket write operation is failed');
        }
    }

    /**
     * Write Unsigned long to the connection
     *
     * @param integer $value
     */
    public function writeULong($value)
    {
        $this->write(pack('L' /* unsigned long, 32 bit, machine byte order */, $value));
    }

    /**
     * Send message
     *
     * @param string $message
     */
    public function sendMessage($message)
    {
        $this->writeULong(strlen($message));
        $this->write($message);
    }
}

class IpcSocketServer extends IpcSocket {
    /**
     * Socket path
     *
     * @var string
     */
    protected $_path;

    /**
     * Socket
     *
     * @var resource
     */
    protected $_socket;

    /**
     * Create socket and start listening
     *
     * @param string $path
     */
    public function __construct($path)
    {
        $this->_path = $path;

        $this->_socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if ($this->_socket === false) {
            $this->throwSocketError('Socket creation is failed');
        }

        if (!socket_set_option($this->_socket, SOL_SOCKET, SO_REUSEADDR, 1)) {
            $this->throwSocketError('Socket set_option operation is failed');
        }

        // try to remove socket first
        @unlink($this->_path);
        if (!socket_bind($this->_socket, $this->_path)) {
            $this->throwSocketError('Socket bind operation is failed');
        }

        if (!socket_listen($this->_socket)) {
            $this->throwSocketError('Socket start listening is failed');
        }

        // Set blocking mode
        if (!socket_set_block($this->_socket)) {
            $this->throwSocketError('Socket set blocking mode is failed');
        }
    }

    public function __destruct()
    {
        socket_close($this->_socket);
        unlink($this->_path);
    }

    public function acceptConnection()
    {
        $ipc_connection = socket_accept($this->_socket);
        if ($ipc_connection === false) {
            $this->throwSocketError('socket_accept operation is failed');
        }

        return new IpcSocketConnection($ipc_connection);
    }
}

