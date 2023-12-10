<?php

declare(strict_types=1);

namespace Platine\Http\Client;

$mock_uniqid = false;
$mock_curl_exec = false;
$mock_curl_error = false;
$mock_curl_getinfo = false;
$mock_curl_setopt_closure = false;

function curl_getinfo($ch)
{
    global $mock_curl_getinfo;
    if ($mock_curl_getinfo) {
        return [
            'url' => 'http://example.com',
            'content_type' => 'application/json',
            'http_code' => 200,
            'header_size' => 2,
            'content_length' => 897,
        ];
    }

    return \curl_getinfo($ch);
}

function curl_setopt($ch, int $option, $value)
{
    global $mock_curl_setopt_closure;
    if ($mock_curl_setopt_closure && is_callable($value)) {
        // TODO
        $value($ch, 'header:value');
    }

    return \curl_setopt($ch, $option, $value);
}


function curl_exec($ch)
{
    global $mock_curl_exec;
    if ($mock_curl_exec) {
        return '  curl_content';
    }

    return \curl_exec($ch);
}


function curl_error($ch)
{
    global $mock_curl_error;
    if ($mock_curl_error) {
        return 'cURL error';
    }

    return \curl_error($ch);
}

function uniqid(string $prefix = "", bool $more_entropy = false)
{
    global $mock_uniqid;
    if ($mock_uniqid) {
        return 'uniqid_key';
    }

    return \uniqid($prefix, $more_entropy);
}

namespace Platine\Http;

$mock_fopen_to_false = false;
$mock_ftell_to_false = false;
$mock_fseek_to_minus_1 = false;
$mock_fseek_throws_exception = false;
$mock_fwrite_to_false = false;
$mock_fwrite_to_zero = false;
$mock_fstat_to_false = false;
$mock_fread_to_false = false;
$mock_php_sapi_name_to_cli = false;
$mock_php_sapi_name_to_apache = false;
$mock_rename_to_false = false;
$mock_rename_to_true = false;
$mock_is_uploaded_file_to_true = false;
$mock_is_uploaded_file_to_false = false;
$mock_move_uploaded_file_to_false = false;
$mock_move_uploaded_file_to_true = false;
$mock_parse_url = false;
$mock_preg_match_to_false = false;
$mock_preg_match_to_true = false;
$mock_strncmp_to_zero = false;
$mock_stream_get_contents_to_false = false;

function stream_get_contents($handle, int $maxlength = -1, int $offset = -1)
{
    global $mock_stream_get_contents_to_false;
    if ($mock_stream_get_contents_to_false) {
        return false;
    } else {
        return \stream_get_contents($handle, $maxlength = -1, $offset = -1);
    }
}

function parse_url(string $url, int $component = -1)
{
    global $mock_parse_url;
    if ($mock_parse_url) {
        return false;
    } else {
        return \parse_url($url, $component);
    }
}

function preg_match(string $pattern, string $subject, array &$matches = null, int $flags = 0, int $offset = 0)
{
    global $mock_preg_match_to_false, $mock_preg_match_to_true;
    if ($mock_preg_match_to_false) {
        return false;
    } elseif ($mock_preg_match_to_true) {
        return true;
    } else {
        return \preg_match($pattern, $subject, $matches, $flags, $offset);
    }
}

function strncmp(string $str1, string $str2, int $len)
{
    global $mock_strncmp_to_zero;
    if ($mock_strncmp_to_zero) {
        return 0;
    } else {
        return \strncmp($str1, $str2, $len);
    }
}

function fwrite($handle, string $string)
{
    global $mock_fwrite_to_zero, $mock_fwrite_to_false;
    if ($mock_fwrite_to_zero) {
        return 0;
    } elseif ($mock_fwrite_to_false) {
        return false;
    } else {
        return \fwrite($handle, $string);
    }
}

function php_sapi_name()
{
    global $mock_php_sapi_name_to_cli, $mock_php_sapi_name_to_apache;
    if ($mock_php_sapi_name_to_cli) {
        return 'cli';
    } elseif ($mock_php_sapi_name_to_apache) {
        return 'apache2handler';
    } else {
        return \php_sapi_name();
    }
}

function rename(string $oldname, string $newname)
{
    global $mock_rename_to_false, $mock_rename_to_true;
    if ($mock_rename_to_false) {
        return false;
    } elseif ($mock_rename_to_true) {
        return true;
    } else {
        return \rename($oldname, $newname);
    }
}

function is_uploaded_file(string $filename)
{
    global $mock_is_uploaded_file_to_true,
    $mock_is_uploaded_file_to_false;
    if ($mock_is_uploaded_file_to_true) {
        return true;
    } elseif ($mock_is_uploaded_file_to_false) {
        return false;
    } else {
        return \is_uploaded_file($filename);
    }
}

function move_uploaded_file(string $from, string $to)
{
    global $mock_move_uploaded_file_to_true,
    $mock_move_uploaded_file_to_false;
    if ($mock_move_uploaded_file_to_true) {
        return true;
    } elseif ($mock_move_uploaded_file_to_false) {
        return false;
    } else {
        return \move_uploaded_file($from, $to);
    }
}

function fopen(string $filename, string $mode)
{
    global $mock_fopen_to_false;
    if ($mock_fopen_to_false) {
        return false;
    } else {
        return \fopen($filename, $mode);
    }
}

function ftell($stream)
{
    global $mock_ftell_to_false;
    if ($mock_ftell_to_false) {
        return false;
    } else {
        return \ftell($stream);
    }
}

function fseek($stream, int $offset, int $whence = SEEK_SET)
{
    global $mock_fseek_to_minus_1,
    $mock_fseek_throws_exception;
    if ($mock_fseek_to_minus_1) {
        return -1;
    } elseif ($mock_fseek_throws_exception) {
        throw new \Exception('Error when using fseek');
    } else {
        return \fseek($stream, $offset, $whence);
    }
}

function fstat($stream)
{
    global $mock_fstat_to_false;
    if ($mock_fstat_to_false) {
        return false;
    } else {
        return \fstat($stream);
    }
}

function fread($stream, int $length)
{
    global $mock_fread_to_false;
    if ($mock_fread_to_false) {
        return false;
    } else {
        return \fread($stream, $length);
    }
}
