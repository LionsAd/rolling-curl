RollingCurl was written by Josh Fraser (joshfraser.com) and is released under the Apache License 2.0
Maintained by Alexander Makarov, http://rmcreative.ru/

 == Overview ==

RollingCurl is a more efficient implementation of curl_multi() curl_multi is a great way to process multiple HTTP requests in parallel in PHP. 
curl_multi is particularly handy when working with large data sets (like fetching thousands of RSS feeds at one time). Unfortunately there is 
very little documentation on the best way to implement curl_multi. As a result, most of the examples around the web are either inefficient or
fail entirely when asked to handle more than a few hundred requests.

The problem is that most implementations of curl_multi wait for each set of requests to complete before processing them. If there are too many requests 
to process at once, they usually get broken into groups that are then processed one at a time. The problem with this is that each group has to wait for 
the slowest request to download. In a group of 100 requests, all it takes is one slow one to delay the processing of 99 others. The larger the number of 
requests you are dealing with, the more noticeable this latency becomes.

The solution is to process each request as soon as it completes. This eliminates the wasted CPU cycles from busy waiting. I also created a queue of 
cURL requests to allow for maximum throughput. Each time a request is completed, I add a new one from the queue. By dynamically adding and removing 
links, we keep a constant number of links downloading at all times. This gives us a way to throttle the amount of simultaneous requests we are sending. 
The result is a faster and more efficient way of processing large quantities of cURL requests in parallel.

 == Usage == 

Example 1 - Hello world:

// an array of URL's to fetch
$urls = array("http://www.google.com",
              "http://www.facebook.com",
              "http://www.yahoo.com");

// a function that will process the returned responses
function request_callback($response, $info) {
	// parse the page title out of the returned HTML
	if (preg_match("~<title>(.*?)</title>~i", $response, $out)) {
		$title = $out[1];
	}
	echo "<b>$title</b><br />";
	print_r($info);
	echo "<hr>";
}

// create a new RollingCurl object and pass it the name of your custom callback function
$rc = new RollingCurl("request_callback");
// the window size determines how many simultaneous requests to allow.  
$rc->window_size = 20;
foreach ($urls as $url) {
    // add each request to the RollingCurl object
    $request = new Request($url);
    $rc->add($request);
}
$rc->execute();





Example 2 - Setting custom options:

Set custom options for EVERY request:

$rc = new RollingCurl("request_callback");
$rc->options = array(CURLOPT_HEADER => true, CURLOPT_NOBODY => true); 
$rc->execute();

Set custom options for A SINGLE request:

$rc = new RollingCurl("request_callback");
$request = new Request($url);
$request->options = array(CURLOPT_HEADER => true, CURLOPT_NOBODY => true); 
$rc->add($request);
$rc->execute();




Example 3 - Shortcuts:

$rc = new RollingCurl("request_callback");
$rc->get("http://www.google.com");
$rc->get("http://www.yahoo.com");
$rc->execute();

Example 4 - Class callbacks:

class MyInfoCollector {
    private $rc;

    function __construct(){
        $this->rc = new RollingCurl(array($this, 'processPage'));
    }

    function processPage($response, $info){
      //...
    }

    function run($urls){
        foreach ($urls as $url){
            $request = new Request($url);
            $this->rc->add($request);
        }
        $this->rc->execute();
    }
}

$collector = new MyInfoCollector();
$collector->run(array(
    'http://google.com/',
    'http://yahoo.com/'
));

$Id$