<?php

namespace pSockets\Utils;

class Logger
{
    private int $logLevel = 0;

    public function __construct(int $logLevel)
    {
        $this->logLevel = $logLevel;
    }

    public function log(string $msg, int $level = 0)
    {
        if($level <= $this->logLevel) echo "\e[36m[INFO]\e[37m[" . date('H:i:s') . "] " . $msg . \PHP_EOL;
    }

    public static function warn(string $msg) : void
    {
        echo "\e[33m[WARN]\e[37m[" . date('H:i:s') . "] " . $msg . \PHP_EOL;
    }

    public static function err(string $msg) : void
    {
        echo "\e[31m[ERR]\e[37m[" . date('H:i:s') . "] " . $msg . \PHP_EOL;
    }
}