<?php

/*
authored by Josh Fraser (www.joshfraser.com)
released under Apache License 2.0
*/

// silly little example that fetches a bunch of sites in parrallel and echos the MD5 of the page content

require("RollingCurl.php");

// function that should process the returned content
// pass this function name as a callback to your RC instance
function request_callback($result) {
    echo md5($result)."<br />";
}

// top 20 sites according to alexa (11/5/09)
$urls = array("http://www.google.com",
              "http://www.facebook.com",
              "http://www.yahoo.com",
              "http://www.youtube.com",
              "http://www.live.com",
              "http://www.wikipedia.com",
              "http://www.blogger.com",
              "http://www.msn.com",
              "http://www.baidu.com",
              "http://www.yahoo.co.jp",
              "http://www.myspace.com",
              "http://www.qq.com",
              "http://www.google.co.in",
              "http://www.twitter.com",
              "http://www.google.de",
              "http://www.microsoft.com",
              "http://www.google.cn",
              "http://www.sina.com.cn",
              "http://www.wordpress.com",
              "http://www.google.co.uk");

$rc = new RollingCurl("request_callback");
$rc->window_size = 20;
foreach ($urls as $url) {
    $request = new Request($url);
    $rc->add($request);
}
$rc->execute();

?>