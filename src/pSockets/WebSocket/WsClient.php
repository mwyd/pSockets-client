<?php

namespace pSockets\WebSocket;

use pSockets\Event;
use pSockets\Stream\StreamSocketClient;
use pSockets\Utils\Logger;
use pSockets\RFC6455\Frame;

abstract class WsClient implements WsConnection
{
    use WsBase
    {
        setPath as private;
        setState as private;
        setHandshake as private;
        handleFrames as private;

        bufferLen as private;
        bufferRead as private;
        bufferPeek as private;
        bufferClear as private;
        bufferWrite as private;
    }

    abstract protected function onOpen() : void;
    abstract protected function onMessage(WsMessage $message) : void;
    abstract protected function onClose() : void;

    public const REQUIRED_RESPONSE_HEADERS = [
        'method'                => '101 Switching Protocols',
        'upgrade'               => 'websocket',
        'connection'            => 'Upgrade',
        'sec-websocket-accept'  => ''
    ];

    private Logger $logger;
    private StreamSocketClient $clientSocket;

    private array $config   = [
        'LOG_LEVEL'             => 1,
        'BUFFER_SIZE'           => 8192,
        'CONNECT_TIMEOUT'       => 10,
        'HANDSHAKE_TIMEOUT'     => 2,
        'ADDITIONAL_HEADERS'    => []
    ];

    private bool $isRunning;

    public function __construct(string $address, array $config = [])
    {
        $this->config = array_merge($this->config, $config);
        $this->logger = new Logger($this->config['LOG_LEVEL']);

        try
        {
            $this->clientSocket = new StreamSocketClient($address, $this->config['CONNECT_TIMEOUT']);
            $this->isRunning = true;
        }
        catch(\Exception $e)
        {
            $this->isRunning = false;
            Logger::err("{$e->getMessage()} : {$e->getCode()}");
        }
    }

    public function __destruct()
    {
        if(isset($this->clientSocket)) $this->clientSocket->close();
    }

    public function run()
    {
        $this->performHandshake();
        $this->handleMessages();
    }

    public function send(string $message, bool $isBinary = false) : void
    {
        if(in_array($this->getState(), [WsConnection::CST_CLOSED, WsConnection::CST_CLOSING]))
        {
            Logger::warn('Unable to send message: client connection state closing or closed');
            return;
        }

        $this->bufferWrite('w', (new Frame)->encode($message, ($isBinary) ? Frame::OP['BIN'] : Frame::OP['TXT'], true)->applyMask());
    }

    public function close() : void
    {
        if(in_array($this->getState(), [WsConnection::CST_CLOSED, WsConnection::CST_CLOSING]))
        {
            Logger::warn('Client already closed');
            return;
        }

        $this->bufferWrite('w', (new Frame)->encode(pack('n', Frame::CLOSE_CODE['NORMAL']), Frame::OP['CLOSE'], true)->applyMask());
        $this->setState(WsConnection::CST_CLOSING);
    }

    public function getAddress() : string
    {
        return $this->clientSocket->getAddress();
    }

    public function getBufferLen(string $type) : int
    {
        return $this->bufferLen($type);
    }

    private function performHandshake() : void
    {
        if(!$this->isRunning) return;

        $key = '';
        $url = $this->clientSocket->getUrl();
        for($i = 0; $i < 16; $i++) $key .= chr(rand(33, 126));

        $request = "GET {$url['path']}" . (!empty($url['query']) ? "?{$url['query']}" : "") . " HTTP/1.1\r\n";
        $request .= "Host: {$url['host']}:{$url['port']}\r\n";
        $request .= !empty($this->config['ADDITIONAL_HEADERS']) ? implode("\r\n", $this->config['ADDITIONAL_HEADERS']) . "\r\n" : "";
        $request .= "Upgrade: websocket\r\n";
        $request .= "Connection: Upgrade\r\n";
        $request .= "Sec-WebSocket-Version: 13\r\n";
        $request .= "Sec-WebSocket-Key: " . base64_encode($key) . "\r\n\r\n";

        $this->setPath($url['path']);
        $this->bufferWrite('w', $request);

        while($this->clientSocket->isAlive() && !$this->getHandshake() && $this->clientSocket->getLifeTime() < $this->config['HANDSHAKE_TIMEOUT'])
        {
            $mode = $this->bufferLen('w') > 0 ? StreamSocketClient::WAIT_RDWR : StreamSocketClient::WAIT_RD;

            foreach($this->clientSocket->wait($mode, 1) as $e)
            {
                switch($e->type)
                {
                    case Event::E_READ:
                        $read = $this->clientSocket->read($this->config['BUFFER_SIZE']);
                        if($read !== false)
                        {
                            $this->bufferWrite('r', $read);
                            $this->verifyHandshake();
                        }
                        break;

                    case Event::E_WRITE:
                        $bytes = $this->clientSocket->write($this->bufferPeek('w'));
                        if($bytes !== false) $this->bufferRead('w', $bytes);
                        break;

                    case Event::E_ERR:
                        $this->clientSocket->close();
                        break;
                }
            }
        }
    }

    private function verifyHandshake() : void
    {
        $buffer = $this->bufferPeek('r');

        if(false === ($pos = strpos($buffer, "\r\n\r\n"))) return;

        $response = splitHeaders($this->bufferRead('r', $pos + 4));

        foreach(self::REQUIRED_RESPONSE_HEADERS as $key => $val)
        {
            if(!isset($response[strtolower($key)]) || false === stripos($response[$key], $val))
            {
                $this->clientSocket->close();
                return;
            }
        }

        $this->setState(WsConnection::CST_OPEN);
        $this->setHandshake(true);

        $this->logger->log('Connected', 1);

        $this->onOpen();
        $this->handleBuffer();
    }

    private function handleMessages() : void
    {
        if(!$this->getHandshake())
        {
            $this->logger::err('Unable to handshake');
            return;
        }

        while($this->clientSocket->isAlive())
        {
            $disconnect = false;
            $mode = $this->bufferLen('w') > 0 ? StreamSocketClient::WAIT_RDWR : StreamSocketClient::WAIT_RD;

            foreach($this->clientSocket->wait($mode, null) as $e)
            {
                switch($e->type)
                {
                    case Event::E_READ:
                        $read = $this->clientSocket->read($this->config['BUFFER_SIZE']);
                        if($read !== false && $this->getState() == WsConnection::CST_OPEN)
                        {
                            $this->bufferWrite('r', $read);
                            $this->handleBuffer();
                        }
                        break;

                    case Event::E_WRITE:
                        $bytes = $this->clientSocket->write($this->bufferPeek('w'));
                        if($bytes !== false) $this->bufferRead('w', $bytes);
                        if($this->bufferLen('w') == 0 && $this->getState() == WsConnection::CST_CLOSING) $disconnect = true;
                        break;

                    case Event::E_ERR:
                        $disconnect = true;
                        break;
                }
            }

            if($disconnect) break;
        }

        $this->disconnect();
    }

    private function handleBuffer() : void
    {
        foreach($this->handleFrames(false) as $e)
        {
            switch($e->type)
            {
                case Event::E_MSG:
                    $this->logger->log("Server sent '{$e->meta}'", 2);
                    $this->OnMessage($e->meta);
                    break;

                case Event::E_DISCONNECT:
                    $this->bufferWrite('w', (new Frame)->encode(pack('n', $e->errno), Frame::OP['CLOSE'], true)->applyMask());
                    $this->setState(WsConnection::CST_CLOSING);
                    break;
            }
        }
    }

    private function disconnect() : void
    {
        $this->clientSocket->close();
        $this->setState(WsConnection::CST_CLOSED);

        $this->logger->log('Disconnected', 1);
        $this->onClose();
    }
}