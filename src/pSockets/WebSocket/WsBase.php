<?php

namespace pSockets\WebSocket;

use pSockets\Event;
use pSockets\ReadWriteBuffer;
use pSockets\RFC6455\Frame;

trait WsBase
{
    use ReadWriteBuffer;

    private bool $handshake             = false;
    private string $path                = '/';

    private bool $pinged                = false;
    private int $state                  = WsConnection::CST_CONNECTING;

    private array $fragmentedMessage    = [];
    private ?int $expectedFrameType     = null;
    private bool $expectedContinuous    = false;

    private int $expectedFrameLen       = 2;

    public function setHandshake(bool $success) : void
    {
        $this->handshake = $success;
    }

    public function getHandshake() : bool
    {
        return $this->handshake;
    }

    public function setPath(string $path) : void
    {
        $this->path = $path;
    }

    public function getPath() : string
    {
        return $this->path;
    }

    public function setState(int $state) : void
    {
        $this->state = $state;
    }

    public function getState() : int
    {
        return $this->state;
    }

    public function handleFrames(bool $requireMask) : \Generator
    {
        while($this->bufferLen('r') >= $this->expectedFrameLen)
        {
            $event = new Event;
            $frame = new Frame;

            if(!$frame->decode($this->bufferPeek('r'))->valid($requireMask))
            {
                $event->type = Event::E_DISCONNECT;
                $event->errno = Frame::CLOSE_CODE['PROTOCOL_ERR'];
                yield $event;
                break;
            }

            if(!$frame->isComplete())
            {
                $this->expectedFrameLen = $frame->len;
                $event->type = Event::E_IDLE;
                yield $event;
                break;
            }

            $this->expectedFrameLen = 2;

            $this->bufferRead('r', $frame->len);
            $frame->applyMask();

            switch($frame->opcode)
            {
                case Frame::OP['CLOSE']:
                    switch($frame->payloadLen)
                    {
                        case 0:
                            $closeCode = Frame::CLOSE_CODE['NORMAL'];
                            break;

                        case 1:
                            $closeCode = Frame::CLOSE_CODE['PROTOCOL_ERR'];
                            break;

                        default:
                            $closeCode = unpack('n', substr($frame->payloadData, 0 ,2))[1];
                            if(in_array($closeCode, Frame::INVALID_CLOSE_CODES)) $closeCode = Frame::CLOSE_CODE['PROTOCOL_ERR'];
                            else if(!isUTF8(substr($frame->payloadData, 2))) $closeCode = Frame::CLOSE_CODE['INVALID_PAYLOAD_DATA'];
                            break;
                    }

                    $event->type = Event::E_DISCONNECT;
                    $event->errno = $closeCode;
                    break;

                case Frame::OP['PING']:
                    $pongFrame = (new Frame)->encode($frame->payloadData, Frame::OP['PONG'], !$requireMask);
                    $this->bufferWrite('w', !$requireMask ? $pongFrame->applyMask() : $pongFrame);
                    break;

                case Frame::OP['PONG']:
                    if($this->pinged)
                    {
                        /* got pong */
                    }
                    break;

                case Frame::OP['CON']:
                    if(!$this->expectedContinuous)
                    {
                        $event->type = Event::E_DISCONNECT;
                        $event->errno = Frame::CLOSE_CODE['PROTOCOL_ERR'];
                    }
                    else if($frame->fin != 1) $this->fragmentedMessage[] = $frame->payloadData;
                    else
                    {
                        $message = implode('', $this->fragmentedMessage) . $frame->payloadData;
                        $isBinary = $this->expectedFrameType == Frame::OP['BIN'];

                        if(!$isBinary && !isUTF8($message))
                        {
                            $event->type = Event::E_DISCONNECT;
                            $event->errno = Frame::CLOSE_CODE['INVALID_PAYLOAD_DATA'];
                        }
                        else
                        {
                            $this->expectedFrameType = null;
                            $this->expectedContinuous = false;
                            $this->fragmentedMessage = [];

                            $event->type = Event::E_MSG;
                            $event->meta = new WsMessage($message, $isBinary);
                        }
                    }
                    break;

                default:
                    if($this->expectedContinuous)
                    {
                        $event->type = Event::E_DISCONNECT;
                        $event->errno = Frame::CLOSE_CODE['PROTOCOL_ERR'];
                    }
                    else if($frame->fin == 0)
                    {
                        $this->expectedContinuous = true;
                        $this->expectedFrameType = $frame->opcode;
                        $this->fragmentedMessage[] = $frame->payloadData;
                    }
                    else
                    {
                        $isBinary = $frame->opcode == Frame::OP['BIN'];

                        if(!$isBinary && !isUTF8($frame->payloadData))
                        {
                            $event->type = Event::E_DISCONNECT;
                            $event->errno = Frame::CLOSE_CODE['INVALID_PAYLOAD_DATA'];
                        }
                        else
                        {
                            $event->type = Event::E_MSG;
                            $event->meta = new WsMessage($frame->payloadData, $isBinary);
                        }
                    }
                    break;
            }

            yield $event;
            if($event->type == Event::E_DISCONNECT) break;
        }
    }
}