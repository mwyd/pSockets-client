<?php

namespace pSockets\WebSocket;

class WsMessage
{
    private string $message;
    private bool $isBinary;

    public function __construct(string $message, bool $isBinary)
    {
        $this->message = $message;
        $this->isBinary = $isBinary;
    }

    public function isBinary() : bool
    {
        return $this->isBinary;
    }

    public function json() : mixed
    {
        return json_decode(json: $this->message, flags: \JSON_THROW_ON_ERROR);
    }

    public function __toString() : string
    {
        return $this->message;
    }
}