# pSockets-client

[![Autobahn Testsuite](https://img.shields.io/badge/Autobahn-passing-brightgreen.svg)](http://40.113.98.97/)

Synchronous PHP websocket client

# Requirements

* PHP ^8.0
* Command prompt or terminal access

# Installation

It is recommended to install package via composer

```
composer require mwyd/psockets
```

# Documentation

```php
abstract class WsClient
{
    /**
    * @var Logger $logger
    */
    protected Logger $logger;
    
    /**  
    * @param string $address valid url
    * @param array $config default values => [
    *   'FLAGS'                 => null,
    *   'CONTEXT'               => null,
    *   'LOG_LEVEL'             => 1,
    *   'BUFFER_SIZE'           => 8192,
    *   'CONNECT_TIMEOUT'       => 10,
    *   'HANDSHAKE_TIMEOUT'     => 2,
    *   'ADDITIONAL_HEADERS'    => []
    * ]
    * @param array['FLAGS']?int alias of socket_create_client flags parameter
    * @param array['CONTEXT']mixed alias of socket_create_client context parameter
    * @param array['CONNECT_TIMEOUT']?float alias of socket_create_client timeout parameter
    * @param array['LOG_LEVEL']int 0 => logs disabled, 1 => connect, disconnect, 2 => connect, disconnect, messages  
    * @param array['HANDSHAKE_TIMEOUT']int seconds
    */
    public function __construct(string $address, array $config);
    
    /**
    *  Method to be called when the connection is opened
    */
    abstract protected function onOpen() : void;
    
    /**
    * Method to be called when a message is received from the server
    */
    abstract protected function onMessage(WsMessage $message) : void;
    
    /**
    * Method to be called when the connection is closed 
    */
    abstract protected function onClose() : void;
    
    /**
    * Writes data to buffer
    * @param string $message message to be written
    * @param bool $isBinary is the message binary
    * @param int $fragmentSize when value is greater than 0 and less than message length data will be sent in many frames otherwise single frame will be sent
    */
    public function send(string $message, bool $isBinary = false, int $fragmentSize = 0) : void;

    /**
    * Closes the connection
    */
    public function close() : void;
    
    /**
    * Returns current connection state 
    * @return int connection state 0 => CST_CONNECTING, 1 => CST_OPEN, 2 => CST_CLOSING, 3 => CST_CLOSED
    */
    public function getState() : int;
    
    /**
    * Returns path
    * @return string path 
    */
    public function getPath() : string;
    
    /**
    * Returns handshake status
    * @return bool success 
    */
    public function getHandshake() : bool;
    
    /**
    * Returns local address
    * @return string local address 
    */
    public function getAdress() : string;
    
    /**
    * Returns amount of bytes in a specified buffer
    * @param string $type buffer type 'r' => read buffer, 'w' => write buffer
    * @return int amount of bytes 
    */
    public function getBufferLen(string $type) : int;
    
    /**
    * Runs the main loop
    */
    public function run() : void;
}

class WsMessage
{
    public function __construct(string $message, bool $isBinary);

    public function isBinary() : bool;
    
    /**
    * @throws \JsonException 
    */
    public function json() : mixed;
    
    public function __toString() : string;
}

class Logger
{
    public function __construct(int $logLevel);
    
    public function log(string $msg, int $level = 0) : void;
    
    public static function warn(string $msg) : void;
    
    public static function err(string $msg) : void;
}
```

# Usage example

```php
<?php

$loader = require_once __DIR__ . "/vendor/autoload.php";

use pSockets\WebSocket\WsClient;
use pSockets\WebSocket\WsMessage;
use pSockets\Utils\Logger;

class MyClient extends WsClient
{
    private int $recvMessages = 0;

    protected function onOpen() : void
    {
        $this->send('Echo message - txt');
        $this->send('Echo message - txt fragmented', fragmentSize: 2);
    }

    protected function onMessage(WsMessage $message) : void
    {
        $this->recvMessages++;

        try
        {
            $json = $message->json();
        }
        catch(\Exception $e)
        {
            Logger::warn($e->getMessage());
        }

        if($this->recvMessages == 2) $this->close();
    }

    protected function onClose() : void
    {
        $this->logger->log('Done');
    }
}

$ws = new MyClient("wss://echo.websocket.org", [
    'LOG_LEVEL' => 2,
    'ADDITIONAL_HEADERS' => [
        'User-Agent: pSockets/Client/0.11',
        'Origin: example.com'
    ]
]);
$ws->run();
```
