<?php
include "vendor/autoload.php";

use Pingink\Protocol\Http\Request;
use Pingink\Protocol\Http\Response;
use Lisachenko\Protocol\FCGI;
use Lisachenko\Protocol\FCGI\FrameParser;
use Lisachenko\Protocol\FCGI\Record\BeginRequest;
use Lisachenko\Protocol\FCGI\Record\Params;
use Lisachenko\Protocol\FCGI\Record\Stdin;
use Lisachenko\Protocol\FCGI\Record\Stdout;

$request = new Request();

//创建
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (! $socket) {
    die("socket_create fail: " . socket_strerror(socket_last_error()) . "\n");
}

//绑定
$result = socket_bind($socket, "0.0.0.0", 80);
if (! $result) {
    die("socket_bind fail: " . socket_strerror(socket_last_error()) . "\n");
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


    //读取消息
    $recv = "";
    $contentLength = null;
    while (true) {
        //socket_read是阻塞调用
        $buffer = socket_read($connect, 100);

        $recv .= $buffer;

        if ($request->parse($recv)) {
            break;
        }

        // 客户端关闭或者超时才会到这里
        if($buffer === false || $buffer === '') {
            echo "client closed.\n";
            socket_close($connect);
            break;
        }
    }

    echo "--------------------------------------------recv--------------------------------------------\n";
    echo $recv . "\n";
    echo "--------------------------------------------recv--------------------------------------------\n";

    $params = [];
    $parts = parse_url($request->getUri());
    if (! empty($parts['query'])) {
        parse_str($parts['query'], $params);
    }

    //cgi返回的数据
    $content = "Hello " . $request->getContent()['name'];

    //发送消息
    socket_write($connect, cgi($params));

    // 关闭链接
    socket_close($connect);

    echo "socket_close connect success \n";
}

//
socket_close($socket);

echo "socket_close socket success \n";


function cgi(array $params)
{
    if (empty($params)) {
        $params['name'] = 'cgi';
    }

    // Let's connect to the local php-fpm daemon directly
    $phpSocket = fsockopen('127.0.0.1', 9000, $errorNumber, $errorString);
    $packet    = '';

    // Prepare our sequence for querying PHP file
    $packet .= new BeginRequest(FCGI::RESPONDER);
    $packet .= new Params([
        'REQUEST_METHOD' => 'GET',
        'SCRIPT_NAME' => '/index.php',
        'DOCUMENT_URI' => '/index.php',
        'DOCUMENT_ROOT' => '/srv/project/php-protocol',
        'SERVER_PROTOCOL' => 'HTTP/1.1',
        'REQUEST_SCHEME' => 'http',
        'GATEWAY_INTERFACE' => 'CGI/1.1',
        'SCRIPT_FILENAME' => '/srv/project/php-protocol/index.php',
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
            if($record instanceof Stdout) {
                $contentData .= $record->getContentData();
            }
        }
    }
    fclose($phpSocket);


    echo "cgi response: \n";
    var_dump($contentData);

    return $contentData;
}