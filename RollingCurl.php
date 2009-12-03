<?php 

/*
authored by Josh Fraser (www.joshfraser.com)
released under Apache License 2.0
*/

// class that represent a single curl request
class Request {
    // stores the url, method, post_data, headers and options for each request
    private $settings = array();

    function __construct($url, $method = "GET", $post_data = null, $headers = null, $options = null) {
        $this->settings['url'] = $url;
        $this->settings['method'] = $method;
        $this->settings['post_data'] = $post_data;
        $this->settings['headers'] = $headers;
        $this->settings['options'] = $options;
    }
    
    public function __get($name) {
        if (isset($this->settings[$name])) {
            return $this->settings[$name];
        }
        return false;
    }

    public function __set($name, $value) {
        $this->settings[$name] = $value;
        return true;
    }
    
    public function __destruct() {
    	unset($this->settings);
	}
}

// class that holds a rolling queue of curl requests
class RollingCurl {

    /* 
    window_size is the max number of simultaneous connections allowed.
    REMEMBER TO RESPECT THE SERVERS.  sending too many requests at one time can easily be perceived as a DOS attack
    increase this window_size if you are making requests to multiple servers or have permission from the receving server admins.
    */
    private $window_size = 5;
    private $callback;
    
    // set your base options that you want to be used with EVERY request
    private $options = array(CURLOPT_SSL_VERIFYPEER => 0,
                             CURLOPT_RETURNTRANSFER => 1,
                             CURLOPT_FOLLOWLOCATION => 1,
                             CURLOPT_MAXREDIRS => 5,
                             CURLOPT_CONNECTTIMEOUT => 30,
                             CURLOPT_TIMEOUT => 30);
    private $headers = array();
    
    // the request queue
    private $requests = array();
    
	function __construct($callback = null) {
        $this->callback = $callback;
    }
    
    public function __get($name) {
        return (isset($this->{$name})) ? $this->{$name} : null;
    }

    public function __set($name, $value){
        // append the base options & headers
        if ($name == "options" || $name == "headers") {
            $this->{$name} = $this->{$name} + $value;
        } else {
            $this->{$name} = $value;
        }
        return true;
    }
    
    // add a request to the request queue
    public function add($request) {
         $this->requests[] = $request;
         return true;        
    }
    
    // or alternatively
    public function request($url, $method = "GET", $post_data = null, $headers = null, $options = null) {
         $this->requests[] = new Request($url, $method, $post_data, $headers, $options);
         return true;
    }
    
    // shortcuts for get / post requests
    public function get($url, $headers = null, $options = null) {
        return $this->request($url, "GET", null, $headers, $options);
    }
    
    public function post($url, $post_data = null, $headers = null, $options = null) {
        return $this->request($url, "POST", $post_data, $headers, $options);
    }
    
    // execute the curl
    public function execute($window_size = null) {
        // rolling curl window must always be greater than 1
        if (sizeof($this->requests) == 1) {
            return $this->single_curl();
        } else {
            // start the rolling curl. window_size is the max number of simultaneous connections 
            return $this->rolling_curl($window_size);
        }
    }   
    
    private function single_curl() {
        $ch = curl_init();        
        $options = $this->get_options($this->requests[0]);
        curl_setopt_array($ch,$options);
        $output = curl_exec($ch);
        $info = curl_getinfo($ch);
        // it's not neccesary to set a callback for one-off requests
        if ($this->callback) {
            $callback = $this->callback;
            $callback($output, $info);
        } else {
            return $output;
        }
    }
        
    private function rolling_curl($window_size = null) {    
        if ($window_size) 
            $this->window_size = $window_size;
            
        // make sure the rolling window isn't greater than the # of urls
        if (sizeof($this->requests) < $this->window_size)
            $this->window_size = sizeof($this->requests);
        
        // window size must be greater than 1
        if ($this->window_size < 2) {
            // TODO: add better error handling
            return "Window size must be greater than 1";
        }
            
        $master = curl_multi_init();
        $curl_arr = array();
            
        // start the first batch of requests
        for ($i = 0; $i < $this->window_size; $i++) {
            $ch = curl_init();
            
            $options = $this->get_options($this->requests[$i]);
                            
            curl_setopt_array($ch,$options);
            curl_multi_add_handle($master, $ch);
        }
            
        do {
            while(($execrun = curl_multi_exec($master, $running)) == CURLM_CALL_MULTI_PERFORM);
            if($execrun != CURLM_OK)
                break;
            // a request was just completed -- find out which one
            while($done = curl_multi_info_read($master)) {

                // get the info and content returned on the request
                $info = curl_getinfo($done['handle']);
                $output = curl_multi_getcontent($done['handle']);

                // send the return values to the callback function.
                $callback = $this->callback;
                $callback($output, $info);

                // start a new request (it's important to do this before removing the old one)
                if ($i < sizeof($this->requests) && isset($this->requests[$i]) && $i < count($this->requests)) {
                    $ch = curl_init();
                    $options = $this->get_options($this->requests[$i++]); 
                    curl_setopt_array($ch,$options);
                    curl_multi_add_handle($master, $ch);
                }

                // remove the curl handle that just completed
                curl_multi_remove_handle($master, $done['handle']);
              
            }
        } while ($running);
        curl_multi_close($master);
        return true;
    }
   
    
     // helper function to set up a new request by setting the appropriate options
    private function get_options($request) {
        // options for this entire curl object
        $options = $this->__get('options');
        $headers = $this->__get('headers');

		// append custom options for this specific request
		if ($request->options) {
            $options = $this->__get('options') + $request->options;
        } 

		// set the request URL
        $options[CURLOPT_URL] = $request->url;

        // posting data w/ this request?
        if ($request->post_data) {
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_POSTFIELDS] = $request->post_data;
        }
        if ($headers) {
            $options[CURLOPT_HEADER] = 0;
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        return $options;
    }
    
    public function __destruct() {
        unset($this->window_size, $this->callback, $this->options, $this->headers, $this->requests);
	}
}

?>