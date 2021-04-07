<?php

namespace pSockets;

trait ReadWriteBuffer
{
    private array $buffer = [
        'read' => '',
        'write' => ''
    ];

    public function bufferWrite(string $type, string $data) : void
    {
        if($type == 'r') $buffer = &$this->buffer['read'];
        else $buffer = &$this->buffer['write'];

        $buffer .= $data;
    }

    public function bufferRead(string $type, int $len) : string
    {
        if($len < 1) return "";

        if($type == 'r') $buffer = &$this->buffer['read'];
        else $buffer = &$this->buffer['write'];

        $data = substr($buffer, 0, $len);
        $buffer = substr($buffer, $len);

        return $data;
    }

    public function bufferPeek(string $type) : string
    {
        return ($type == 'r') ? $this->buffer['read'] : $this->buffer['write'];
    }

    public function bufferClear(string $type) : void
    {
        if($type == 'r') $buffer = &$this->buffer['read'];
        else $buffer = &$this->buffer['write'];

        $buffer = '';
    }

    public function bufferLen(string $type) : int
    {
        return ($type == 'r') ? strlen($this->buffer['read']) : strlen($this->buffer['write']);
    }
}