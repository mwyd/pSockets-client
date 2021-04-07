<?php

namespace pSockets;

class Event
{
    public const E_READ             = 10;
    public const E_WRITE            = 11;
    public const E_ERR              = 12;

    public const E_MSG              = 14;
    public const E_DISCONNECT       = 15;
    public const E_IDLE             = 16;

    public mixed $type              = null;
    public mixed $target            = null;
    public mixed $meta              = null;
    public ?string $error           = null;
    public ?int $errno              = null;
}