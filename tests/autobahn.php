<?php

$loader = require_once __DIR__ . "/../vendor/autoload.php";

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

// /runCase?casetuple=1.1.1&agent=pSocket-client
// /runCase?case=1&agent=pSocket-client

if($argc < 2) exit(
    'Missing command parameter' . \PHP_EOL .
    'Available commands: ' . \PHP_EOL .
    "\t--run-all - runs all tests" . \PHP_EOL .
    "\t--run-case <case-number> - runs specified case" . \PHP_EOL .
    "\t\t--run-case 9.1.6" 
);

switch($argv[1])
{
    case '--run-all':
        for($i = 1; $i < 518; $i++)
        {
            $ws = new MyClient("ws://127.0.0.1:9001/runCase?case={$i}&agent=pSocket-client", [
                'HANDSHAKE_TIMEOUT'     => 1,
                'BUFFER_SIZE'           => 8 * 1024,
                'LOG_LEVEL'             => 1
            ]);
            $ws->run();
        }
        break;

    case '--run-case':
        if($argc < 3) echo 'Unknown case number' . \PHP_EOL;
        else
        {
            $ws = new MyClient("ws://127.0.0.1:9001/runCase?casetuple={$argv[2]}&agent=pSocket-client");
            $ws->run();
        }
        break;

    default:
        echo 'Unknown command' . \PHP_EOL;
        break;
}
