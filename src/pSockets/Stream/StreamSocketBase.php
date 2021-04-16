<?php

namespace pSockets\Stream;

trait StreamSocketBase
{
    private mixed $url;
    private mixed $stream;
    private string $address;
    private int $created;

    private array $err      = [
        'message'   => '',
        'code'      => null
    ];

    private bool $block     = true;

    public function setBlocking(bool $enable) : bool
    {
        $success = stream_set_blocking($this->stream, $enable);
        if($success) $this->block = $enable;

        return $success;
    }

    public function isBlocking() : bool
    {
        return $this->block;
    }

    public function isAlive() : bool
    {
        return is_resource($this->stream);
    }

    public function shutdown(int $mode) : bool
    {
        return stream_socket_shutdown($this->stream, $mode);
    }

    public function close() : bool
    {
        return $this->isAlive() ? fclose($this->stream) : false; 
    }

    public function getAddress() : string
    {
        return $this->address;
    }

    public function setTimeout(int $seconds, int $microseconds) : bool
    {
        return stream_set_timeout($this->stream, $seconds, $microseconds);
    }

    public function getUrl() : array
    {
        return $this->url;
    }

    public function getStream() : mixed
    {
        return $this->stream;
    }

    public function getLifeTime() : int
    {
        return time() - $this->created;
    }

    public function getLastError() : string
    {
        return $this->err['message'];
    }

    public function getLastErrno() : ?int
    {
        return $this->err['code'];
    }
}