<?php

namespace pSockets\Stream;

use pSockets\Event;

class StreamSocketClient
{
    use StreamSocketBase;

    public const WAIT_RD    = 0;
    public const WAIT_WR    = 1;
    public const WAIT_RDWR  = 2;

    public function __construct(string $url, int $timeout)
    {
        $this->url = parse_url($url);

        $this->url['scheme'] = $this->url['scheme'] ?? 'tcp';

        switch($this->url['scheme'])
        {
            case 'wss':
                $this->url['scheme'] = 'ssl';
                $this->url['port'] = $this->url['port'] ?? 443;
                break;

            case 'ws':
                $this->url['scheme'] = 'tcp';
                $this->url['port'] = $this->url['port'] ?? 80;
                break;

            default:
                $this->url['port'] = $this->url['port'] ?? 80;
                break;
        }

        $this->url['host'] = $this->url['host'] ?? '127.0.0.1';
        $this->url['path'] = $this->url['path'] ?? '/';
        $this->url['query'] =  $this->url['query'] ?? '';

        $this->stream = stream_socket_client(
            "{$this->url['scheme']}://{$this->url['host']}:{$this->url['port']}",
            $this->err['code'],
            $this->err['message'],
            $timeout
        );

        if(!$this->stream) throw new \Exception($this->err['message'], $this->err['code']);

        $this->address = stream_socket_get_name($this->stream, false);
        $this->created = time();
    }

    public function wait(int $mode, ?int $tvSec, int $tvuSec = 0) : \Generator
    {
        $event = new Event;

        $e = match($mode) {
            self::WAIT_RD => [
                'r' => [$this->stream],
                'w' => null,
                'e' => null
            ],
            self::WAIT_WR => [
                'r' => null,
                'w' => [$this->stream],
                'e' => null
            ],
            default => [
                'r' => [$this->stream],
                'w' => [$this->stream],
                'e' => null
            ]
        };

        $err = stream_select($e['r'], $e['w'], $e['e'], $tvSec, $tvuSec);

        if($err === false)
        {
            $event->type = Event::E_ERR;
            yield $event;
        }
        else
        {
            if(!empty($e['r']))
            {
                $event->type = Event::E_READ;
                yield $event;
            }

            if(!empty($e['w']))
            {
                $event->type = Event::E_WRITE;
                yield $event;
            }
        }
    }

    public function read(int $bufferSize) : false|string
    {
        return fread($this->stream, $bufferSize);
    }

    public function write(string $data) : false|int
    {
        return fwrite($this->stream, $data, strlen($data));
    }
}