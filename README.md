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

        if($this->recvMessages == 3) $this->close();
    }

    protected function onClose() : void
    {
        $this->logger->log('Done');
    }
}

$ws = new MyClient("wss://echo.websocket.org", [
    'LOG_LEVEL' => 2
]);
$ws->run();
```
