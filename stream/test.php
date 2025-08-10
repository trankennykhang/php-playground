<?php
$fp = stream_socket_client("tcp://dev.to:80", $errno, $errstr, 30);
if (!$fp) {
    echo "$errstr ($errno)<br />\n";
} else {
    fwrite($fp, "GET / HTTP/1.1\r\nHost: dev.to\r\nAccept: */*\r\n\r\n");
    while (!feof($fp)) {
        print 123;
        echo fgets($fp, 1024);
    }
    fclose($fp);
}
?>