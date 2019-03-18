<?php

include_once "config.php";

$useSSL = true;


// If you wanna use this, you have to edit:
// vendor/guzzle/guzzle/src/Guzzle/Http/Client.php ::setSslVerification() and set $certificateAuthority=false

// http://www.devdungeon.com/content/how-use-ssl-sockets-php
// openssl req -x509 -newkey rsa:4096 -keyout dummy_purge_server/key.pem -out dummy_purge_server/domain.crt -days 365 -nodes

function startServer($useSSL, $listenAddress)
{
    global $pem_passphrase;
    global $key;
    global $crt;

    $context = stream_context_create();
    if ($useSSL) {
        // local_cert must be in PEM format
        stream_context_set_option($context, 'ssl', 'local_cert', $crt);
        stream_context_set_option($context, 'ssl', 'local_pk', $key);
        stream_context_set_option($context, 'ssl', 'passphrase', $pem_passphrase);
        stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
        stream_context_set_option($context, 'ssl', 'verify_peer', false);
        $listenAddress = 'ssl://' . $listenAddress;
    }

    // Create the server socket
    $server = stream_socket_server($listenAddress, $errNo, $errStr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
    if ($errNo !== 0) {
        var_dump("Error $errNo : $errStr");
        exit(1);
    }

    return $server;
}

function writeOK($client)
{
    $result = 'Hello world!' . microtime(true) . "\n";
    $contentLength = strlen($result);
    $reply = "HTTP/1.1 200 OK\r\n"
        . "Connection: close\r\n"
        . "Content-Type: text/html\r\n"
        . "Content-Length: $contentLength\r\n"
        . "\r\n"
        . $result;
    fwrite($client, $reply);
}

$server = startServer($useSSL, $listenAddress);
while (true) {
    $buffer = '';
    echo "Waiting for connection...\n###################################################\n";
    $client = stream_socket_accept($server);
    if ($client) {
        // Read until double CRLF
        while (!preg_match('/\r?\n\r?\n/', $buffer)) {
            $buffer .= fread($client, 2046);
        }
        echo $buffer;
        // Respond to client
        writeOK($client);
        fclose($client);
    }
}
