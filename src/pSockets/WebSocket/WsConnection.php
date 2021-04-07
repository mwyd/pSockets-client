<?php

namespace pSockets\WebSocket;

interface WsConnection
{
    public const CST_CONNECTING    = 0;
    public const CST_OPEN          = 1;
    public const CST_CLOSING       = 2;
    public const CST_CLOSED        = 3;

    public function send(string $message, bool $isBinary = false) : void;
    public function close() : void;
    public function getState() : int;
    public function getAddress() : string;
    public function getBufferLen(string $type) : int;
}