<?php

use Pingink\Protocol\Http\Request;
use Pingink\Protocol\Http\Response;
use Lisachenko\Protocol\FCGI;
use Lisachenko\Protocol\FCGI\FrameParser;
use Lisachenko\Protocol\FCGI\Record;
use Lisachenko\Protocol\FCGI\Record\BeginRequest;
use Lisachenko\Protocol\FCGI\Record\Params;
use Lisachenko\Protocol\FCGI\Record\Stdin;
use Lisachenko\Protocol\FCGI\Record\Stdout;
use Lisachenko\Protocol\FCGI\Record\EndRequest;


include "vendor/autoload.php";

http();

function http()
{
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (!$socket) {
        die("create server fail:" . socket_strerror(socket_last_error()) . "\n");
    }

//绑定
    $ret = socket_bind($socket, "0.0.0.0", 80);
    if (!$ret) {
        die("bind server fail:" . socket_strerror(socket_last_error()) . "\n");
    }

//监听
    $ret = socket_listen($socket, 2);
    if (! $ret) {
        die("listen server fail:" . socket_strerror(socket_last_error()) . "\n");
    }

    echo "waiting client...\n";

    while (true) {
        //阻塞等待客户端连接
        $connect = socket_accept($socket);
        if (! $connect) {
            echo "accept server fail:" . socket_strerror(socket_last_error()) . "\n";
            break;
        }

        echo "client connect success.\n";

        //循环读取消息
        $buffer = socket_read($connect, 4096);

        echo "recv: $buffer \n";

        $request = new Request($buffer);

        $params = [];
        $parts = parse_url($request->uri);
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $params);
        }


//        cgi($params);
//        $response = new Response("aa");

        //发送消息
        socket_write($connect, cgi($params));

        // 关闭链接
        socket_close($connect);

        echo "server close success \n";
    }

}



function cgi(array $params)
{
    if (empty($params)) {
        $params['name'] = 'cgi';
    }

    // Let's connect to the local php-fpm daemon directly
    $phpSocket = fsockopen('127.0.0.1', 9000, $errorNumber, $errorString);
    $packet    = '';

    // Prepare our sequence for querying PHP file
    $packet .= new BeginRequest(FCGI::RESPONDER);;
    $packet .= new Params([
        'REQUEST_METHOD' => 'GET',
        'SCRIPT_NAME' => '/index.php',
        'DOCUMENT_URI' => '/index.php',
        'DOCUMENT_ROOT' => '/srv/test',
        'SERVER_PROTOCOL' => 'HTTP/1.1',
        'REQUEST_SCHEME' => 'http',
        'GATEWAY_INTERFACE' => 'CGI/1.1',
        'SCRIPT_FILENAME' => '/srv/project/index.php',
    ]);
    $packet .= new Params($params);
    $packet .= new Stdin("hello world");

    fwrite($phpSocket, $packet);

    $content = '';
    $contentData = '';
    while ($buffer = fread($phpSocket, 4096)) {
        $content .= $buffer;
        while (FrameParser::hasFrame($content)) {
            $record = FrameParser::parseFrame($content);
            var_dump($record);
            if($record instanceof Stdout) {
                $contentData .= $record->getContentData();
            }
        }
    }
    fclose($phpSocket);

    return $contentData;
}