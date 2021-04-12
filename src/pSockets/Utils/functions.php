<?php

function isUTF8(string $s) : bool
{
    return 1 == preg_match('//u', $s);
}

function splitHeaders(string $headers) : array
{
    $exploded = [];

    foreach(preg_split('/\r\n/', $headers) as $key => $val)
    {
        if($key == 0)
        {
            $exploded['__head__'] = $val;
            continue;
        }

        if(empty($val) || !str_contains($val, ':')) continue;

        $header = explode(':', $val, 2);
        $exploded[strtolower($header[0])] = trim($header[1]);
    }

    return $exploded;
}