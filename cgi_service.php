<?php

use Lisachenko\Protocol\FCGI;
use Lisachenko\Protocol\FCGI\FrameParser;
use Lisachenko\Protocol\FCGI\Record\BeginRequest;
use Lisachenko\Protocol\FCGI\Record\Params;
use Lisachenko\Protocol\FCGI\Record\Stdout;
use Lisachenko\Protocol\FCGI\Record\EndRequest;

include "vendor/autoload.php";

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (! $socket) {
    die("socket_create fail:" . socket_strerror(socket_last_error()) . "\n");
}

//绑定
$result = socket_bind($socket, "0.0.0.0", 9000);
if (! $result) {
    die("socket_bind fail:" . socket_strerror(socket_last_error()) . "\n");
}

//监听
$result = socket_listen($socket, 2);
if (! $result) {
    die("socket_listen fail:" . socket_strerror(socket_last_error()) . "\n");
}

echo "waiting client connect...\n";

while (true) {
    //等待客户端连接, socket_accept是阻塞调用
    $connect = socket_accept($socket);
    if (! $connect) {
        echo "socket_accept fail:" . socket_strerror(socket_last_error()) . "\n";
        break;
    }

    echo "client connect success.\n";

    //循环读取消息
    $buffer = socket_read($connect, 65535);

    $data = unpack(FCGI::HEADER_FORMAT, $buffer);


    echo "------------------------------------------recv------------------------------------------\n";
    //echo "$buffer \n";
    echo implode(" ", getBytes($buffer)) . "\n";
    echo "------------------------------------------recv------------------------------------------\n";
    

    $requestId = 0;
    $params = [];
    while (FrameParser::hasFrame($buffer)) {
        $record = FrameParser::parseFrame($buffer);
        var_dump($record);
        if ($record instanceof BeginRequest) {
            $requestId = $record->getRequestId();
        } elseif ($record instanceof Params) {
            $params = $record->getValues();
        }
    }

    $name = $params['name'] ?? "nobody";

    $body = "hello $name";
    $bodySize = strlen($body);
    $messages = [
        new Stdout("HTTP/1.1 200 OK\r\nContent-Type: text/plain\r\nContent-Length: {$bodySize}\r\n\r\n{$body}"),
        new EndRequest(FCGI::REQUEST_COMPLETE, $appStatus = 0),
    ];
    $responseContent = '';
    foreach ($messages as $message) {
        $message->setRequestId(1);
        $responseContent .= $message;
    }

    echo "server response: \n";

    //发送消息
    socket_write($connect, $responseContent);

    // 关闭链接
    socket_close($connect);

    echo "socket_close connect success. \n";
}

socket_close($socket);

echo "socket_close socket success. \n";

function getBytes(string $data)
{
    $bytes = [];
    $count = strlen($data);
    for ($i = 0; $i < $count; ++$i) {
        $byte = ord($data[$i]);
        $bytes[] = $byte;
    }

    return $bytes;
}
