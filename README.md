# pSockets-client

Synchronous PHP websocket client

# Installation

```
composer require mwyd/psockets
```

# Usage

```php
<?php

$loader = require_once __DIR__ . "/vendor/autoload.php";

use pSockets\WebSocket\WsClient;
use pSockets\WebSocket\WsMessage;

class MyClient extends WsClient
{

    protected function onOpen() : void
    {

    }

    protected function onMessage(WsMessage $message) : void
    {
        try
        {
            $json = $message->json();
            $this->send($message, $message->isBinary());
        }
        catch(\Exception $e)
        {

        }
    }

    protected function onClose() : void
    {

    }
}

$ws = new MyClient("wss://ws.example.com/websocket?q=query", [
    'LOG_LEVEL'             => 2,
    'BUFFER_SIZE'           => 8192,
    'CONNECT_TIMEOUT'       => 10,
    'HANDSHAKE_TIMEOUT'     => 2,
    'ADDITIONAL_HEADERS'    => [
        'Cookie: cookie_name=cookie_value;'
    ]
]);
$ws->run();
```
