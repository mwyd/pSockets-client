<?php

namespace pSockets\RFC6455;

// 0                   1                   2                   3
// 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
// +-+-+-+-+-------+-+-------------+-------------------------------+
// |F|R|R|R| opcode|M| Payload len |    Extended payload length    |
// |I|S|S|S|  (4)  |A|     (7)     |             (16/64)           |
// |N|V|V|V|       |S|             |   (if payload len==126/127)   |
// | |1|2|3|       |K|             |                               |
// +-+-+-+-+-------+-+-------------+ - - - - - - - - - - - - - - - +
// |     Extended payload length continued, if payload len == 127  |
// + - - - - - - - - - - - - - - - +-------------------------------+
// |                               |Masking-key, if MASK set to 1  |
// +-------------------------------+-------------------------------+
// | Masking-key (continued)       |          Payload Data         |
// +-------------------------------- - - - - - - - - - - - - - - - +
// :                     Payload Data continued ...                :
// + - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - +
// |                     Payload Data continued ...                |
// +---------------------------------------------------------------+

class Frame
{
    // opcodes
    public const OP = [
        'CON'   => 0,
        'TXT'   => 1,
        'BIN'   => 2,
        'CLOSE' => 8,
        'PING'  => 9,
        'PONG'  => 10
    ];

    public const INVALID_CLOSE_CODES = [
        0,
        999,
        1004,
        1005,
        1006,
        1016,
        1100,
        2000,
        2999
    ];

    public const CLOSE_CODE = [
        'NORMAL'                => 1000,
        'GOING_AWAY'            => 1001,
        'PROTOCOL_ERR'          => 1002,
        'UNSUPPORTED_DATA'      => 1003,
        'INVALID_PAYLOAD_DATA'  => 1007,
        'POLICY_VIOLATION'      => 1008,
        'MSG_TOO_BIG'           => 1009,
    ];

    public int $fin;
    public int $rsv;
    public int $opcode;
    public int $maskBit;
    public int $payloadLen;
    public int $extPayloadLen;
    public string $maskingKey;
    public string $payloadData;
    public int $len;

    public function __construct()
    {
        $this->clear();
    }

    public function clear() : self
    {
        $this->fin                  = 0;
        $this->rsv                  = 0;
        $this->opcode               = 0;
        $this->maskBit              = 0;
        $this->payloadLen           = 0;
        $this->extPayloadLen        = 0;
        $this->maskingKey           = '';
        $this->payloadData          = '';
        $this->len                  = 0;

        return $this;
    }

    public function decode(string $buffer) : self
    {
        $this->clear();

        if(strlen($buffer) < 2) return $this;

        $firstByte = unpack('C', $buffer[0])[1];
        $secondByte = unpack('C', $buffer[1])[1];

        $this->fin = $firstByte & 0b10000000 ? 1 : 0;
        $this->rsv = $firstByte & 0b01110000;
        $this->opcode = $firstByte & 0b00001111;

        if($secondByte > 127)
        {
            $this->maskBit = 1;
            $this->payloadLen = $secondByte - 128;
        }
        else
        {
            $this->maskBit = 0;
            $this->payloadLen = $secondByte;
        }

        $this->len += 2;

        switch($this->payloadLen)
        {
            case 127:
                $extPayloadLen = substr($buffer, 2, 8);
                if(strlen($extPayloadLen) == 8)
                {
                    $this->extPayloadLen = unpack('J', $extPayloadLen)[1];
                    $this->payloadData = substr($buffer, ($this->maskBit == 1) ? 14 : 10, $this->extPayloadLen);
                    $this->len += 8;
                }
                break;

            case 126:
                $extPayloadLen = substr($buffer, 2, 2);
                if(strlen($extPayloadLen) == 2)
                {
                    $this->extPayloadLen = unpack('n', $extPayloadLen)[1];
                    $this->payloadData = substr($buffer, ($this->maskBit == 1) ? 8 : 4, $this->extPayloadLen);
                    $this->len += 2;
                }
                break;

            default:
                $this->extPayloadLen = $this->payloadLen;
                $this->payloadData = substr($buffer, ($this->maskBit == 1) ? 6 : 2, $this->extPayloadLen);
                break;
        }

        $this->len += $this->extPayloadLen;

        if($this->maskBit == 1)
        {
            $this->maskingKey = match($this->payloadLen) {
                127 => substr($buffer, 10, 4),
                126 => substr($buffer, 4, 4),
                default => substr($buffer, 2, 4)
            };

            $this->len += 4;
        }

        return $this;
    }

    public function encode(string $data, int $opcode = self::OP['TXT'], bool $mask = false, bool $fin = true) : self
    {
        $this->clear();

        $this->fin = $fin ? 1 : 0;
        $this->rsv = 0;
        $this->opcode = $opcode;
        $this->maskBit = $mask ? 1 : 0;
        $this->payloadData = $data;
        $this->extPayloadLen = strlen($data);

        $this->payloadLen = match(true) {
            $this->extPayloadLen < 126 => $this->extPayloadLen,
            $this->extPayloadLen < 65536 => 126,
            default => 127
        };

        if($this->maskBit == 1) for($i = 0; $i < 4; $i++) $this->maskingKey .= pack('C', rand(0, 255));

        $this->len = 2 + ($this->maskBit == 1 ? 4 : 0) + $this->extPayloadLen;
        $this->len += match($this->payloadLen) {
            127 => 8,
            126 => 2,
            default => 0
        };

        return $this;
    }

    public function applyMask() : self
    {
        if($this->maskBit == 1 && strlen($this->maskingKey) == 4)
        {
            for($i = 0; $i < $this->extPayloadLen; $i++) $this->payloadData[$i] = $this->payloadData[$i] ^ $this->maskingKey[$i % 4];
        }

        return $this;
    }

    public function __toString() : string
    {
        $firstByte = ($this->fin == 1 ? 128 : 0) + $this->rsv + $this->opcode;
        $secondByte = ($this->maskBit == 1 ? 128 : 0) + $this->payloadLen;

        $extPayloadLen = match($this->payloadLen) {
            127 => pack('J', $this->extPayloadLen),
            126 => pack('n', $this->extPayloadLen),
            default => ''
        };

        return pack('CC', $firstByte, $secondByte) . $extPayloadLen .  $this->maskingKey . $this->payloadData;
    }

    public function valid(bool $requireMask) : bool
    {
        // rsv set
        if($this->rsv != 0) return false;

        // not masked
        if($this->maskBit == 0 && $requireMask) return false;

        // not supported opcode
        if(!in_array($this->opcode, self::OP)) return false;

        // control frame fragmented or payload len > 125
        if(in_array($this->opcode, [self::OP['CLOSE'], self::OP['PING'], self::OP['PONG']]))
        {
            if($this->fin != 1 || $this->payloadLen > 125) return false;
        }

        return true;
    }

    public function isComplete() : bool
    {
        return $this->extPayloadLen >= $this->payloadLen && strlen($this->payloadData) == $this->extPayloadLen;
    }
}