<?php
/**
 * Use curl to login to wiki.kenny.click
 * Maintain the session to browse the page.
 */
$url = "https://wiki.kenny.click";
$user = "";
$pass = "";

/// Initial the session
$ch = curl_init();

// set URL and other appropriate options
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_VERBOSE, 1);

$headers = [
    'X-Apple-Tz: 0',
    'X-Apple-Store-Front: 143444,12',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    'Accept-Encoding: gzip, deflate',
    'Accept-Language: en-US,en;q=0.5',
    'Cache-Control: no-cache',
    'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
    'Host: www.example.com',
    'Referer: http://www.example.com/index.php', // Your referrer address
    'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:28.0) Gecko/20100101 Firefox/28.0',
    'X-MicrosoftAjax: Delta=true'
];

// grab URL and pass it to the browser
curl_exec($ch);

// close cURL resource, and free up system resources
curl_close($ch);