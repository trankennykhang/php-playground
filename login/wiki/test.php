<?php
/**
 * Use curl to login to wiki.kenny.click
 * Maintain the session to browse the page.
 */
$url = "https://auth.nbnco.net.au/okta/login";
$user = "";
$pass = "";


$header = [];
$header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
$header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
$header[] = "Cache-Control: max-age=0";
$header[] = "Connection: keep-alive";
$header[] = "Keep-Alive: 300";
$header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
$header[] = "Accept-Language: en-us,en;q=0.5";
$header[] = "Pragma: "; // browsers keep this blank.

/// Initial the session
$ch = curl_init();

// set URL and other appropriate options
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_VERBOSE, 1);
curl_setopt($ch, CURLOPT_COOKIESESSION, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
//curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//curl_setopt( $ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

// grab URL and pass it to the browser
$html = curl_exec($ch);
if(curl_error($ch)) {
    print curl_error($ch);
} else print_r($html);

// close cURL resource, and free up system resources
curl_close($ch);