<?php

$localPort = '1234';

class DummyPurgeServer
{
    private $localHost;
    private $localPort;
    private $socket = null;

    const BUFSIZE = 2048;

    public function __construct($localHost, $localPort)
    {
        $this->localHost = $localHost;
        $this->localPort = $localPort;
    }

    public function listenToLocalPort()
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->assertSuccess($this->socket, 'Socket not valid');

        $result = socket_bind($this->socket, $this->localHost, $this->localPort);
        $this->assertSuccess($result, 'SocketBind not valid');

        $result = socket_listen($this->socket, 5);
        $this->assertSuccess($result, 'Listen to port did not succeed');
    }

    public function returnOK($resource)
    {
        $msg = "200 OK HTTP/1.1\r\n"
            . "Connection: close\r\n"
            . "Content-Type: text/html\r\n"
            . "\r\n";
        socket_write($resource, $msg, strlen($msg));
    }

    public function waitForConnection()
    {
        while (true) {
            echo  "Waiting for connection\n";
            $resource = socket_accept($this->socket);
            echo "Incomming connection detected\n";
            $this->outputRequest($resource);
            $this->returnOK($resource);
            //$targetConnection = $this->openTargetConnection();
            //$this->relayConnection($resource, $targetConnection);
            echo  "Closing connection to client\n";
            socket_shutdown($resource, 2);
            socket_close($resource);
            //echo  "Closing connection to target\n";
            //socket_shutdown($targetConnection, 2);
            //socket_close($targetConnection);
        }
        echo  "Closing listing port\n";
        $arrOpt = array('l_onoff' => 1, 'l_linger' => 0);
        socket_set_block($this->socket);
        socket_set_option($this->socket, SOL_SOCKET, SO_LINGER, $arrOpt);
        socket_shutdown($this->socket, 2);
        socket_close($this->socket);
    }

    public function openTargetConnection()
    {
        /* Get the IP address for the target host. */
        $address = gethostbyname($this->targetHost);

        /* Create a TCP/IP socket. */
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            echo 'socket_create() failed: reason: ' . socket_strerror(socket_last_error()) . "\n";
        } else {
            echo "OK.\n";
        }

        echo "Attempting to connect to '$address' on port '$this->targetPort'...";
        $result = socket_connect($socket, $address, $this->targetPort);
        if ($result === false) {
            echo "socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
        } else {
            echo "OK.\n";
        }

        return $socket;
    }

    public function outputRequest($resource)
    {
        $input = $this->readFromSocketIfAnyDataAvailable($resource);
        echo "###################################################################################\n";
        echo "$input\n";
    }

    public function forwardSocketData($inputResourceName, $inputResource, $targetResource)
    {
        // Check if client is sending any data
        //echo( "Reading from $inputResourceName...\n" );
        $input = $this->readFromSocketIfAnyDataAvailable($inputResource);
        if ($input === false) {
            echo "Connection closed by $inputResourceName\n";

            return false;
        }

        // If we read something from client, pass it on to target
        if (($input !== false) and (strlen($input) > 0)) {
            if ($inputResourceName === 'Client') {
                echo  "###################################################################################\n";
                $headerString = ' - Client request BEGIN - ';
                $footerString = '# - Client request END - #';
            } else {
                $headerString = '# - Target reply BEGIN - #';
                $footerString = '## - Target reply END - ##';
            }
            echo  "Received from $inputResourceName:\n"
                . "####################{$headerString}#########################\n"
                . "$input\n"
                . "####################{$footerString}#########################\n";
            // Send request to target
            echo  'sending to target...';
            socket_write($targetResource, $input, strlen($input));
            echo  "done.\n";
        }

        return true;
    }

    public function relayConnection($inputResource, $targetResource)
    {
        $count = 0;

        while (true) {
            ++$count;

            if (false === $this->forwardSocketData('Client', $inputResource, $targetResource)) {
                break 1;
            }

            if (false === $this->forwardSocketData('Target', $targetResource, $inputResource)) {
                break 1;
            }

            usleep(50000);
        }
        //socket_close( $inputResource );
    }

    public function readFromSocketIfAnyDataAvailable($resource)
    {
        //        $input = socket_read( $resource, tcpRelayer::BUFSIZE, PHP_NORMAL_READ );
//        return "$input\n";
        $buf = '';
        $returnValue = '';
//        sleep (1);
        socket_clear_error($resource);
        $lastError = 0;
        $startTime = mktime();
        do {
            $bytes = @socket_recv($resource, $buf, 2048, MSG_DONTWAIT);
            $returnValue .= $buf;
            $lastError = socket_last_error($resource);
            if (($returnValue === '') and ($lastError !== 0)) {
                echo ".\n";
                if ($startTime + 30 < mktime()) {
                    echo 'Timeout error: client used more than 30 seconds';
                    break;
                }
                continue;
            }
        } while ((($bytes !== false) and ($bytes > 0)));
//        var_dump( "bytes", $bytes, socket_last_error( $resource ) );
//        $lastError = socket_last_error($resource);
//        echo( "Socket error $lastError : " . socket_strerror( $lastError ) . "\n" );

        // We will actually not check if $bytes is false. Instead we'll check if we get any data at all.
        // if bytes is false, this function will instead return false next time it is called....
        /*if( $bytes === false )
        {
            echo( "Socket error $lastError : " . socket_strerror( $lastError ) . "\n" );
        }*/

        // If remote computer closes connection, we'll get 0 bytes but success status, *not* "Socket error 11 : Resource temporarily unavailable"
        if (($returnValue === '') and ($lastError === 0)) {
            return false;
        }

        return $returnValue;
    }

    public function assertSuccess($value, $message)
    {
        if ($value === null) {
            var_dump("Aborting because value is null : $message");
            die;
        }

        if ($value === false) {
            var_dump("Aborting because value is false : $message");
            die;
        }
    }
}

//$relay = new tcpRelayer( 8080, 'foobar.com', 80);
//require_once 'config.php';

$relay = new DummyPurgeServer('192.168.0.1', $localPort);
$relay->listenToLocalPort();
$relay->waitForConnection();
