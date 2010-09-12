<?
/*

  Authored by Fabian Franz (www.lionsad.de)
  Released under Apache License 2.0

$Id$
*/

class RollingCurlGroupException extends Exception {}

abstract class RollingCurlGroupRequest extends RollingCurlRequest
{
        private $group = null;

	/**
	 * Set group for this request
	 *
	 * @param group The group to be set
	 */
        function setGroup($group)
        {
                if (!($group instanceof RollingCurlGroup))
                        throw new RollingCurlGroupException("setGroup: group needs to be of instance RollingCurlGroup");

                $this->group = $group;
        }

	/**
	 * Process the request
	 *
	 *
	 */
        function process($output, $info)
        {
                if ($this->group)
                        $this->group->process($output, $info, $this);
        }

	/**
	 * @return void
	 */
	public function __destruct() {
		unset($this->group);
		parent::__destruct();
	}

}

class RollingCurlGroup
{
        protected $name;
        protected $num_requests = 0;
        protected $finished_requests = 0;
        private $requests = array();

        function __construct($name)
        {
                $this->name = $name;
        }

	/**
	 * @return void
	 */
	public function __destruct() {
		unset($this->name, $this->num_requests, $this->finished_requests, $this->requests);
	}


        function add($request)
        {
                if ($request instanceof RollingCurlGroupRequest)
                {
                        $request->setGroup($this);
                        $this->num_requests++;
                        $this->requests[] = $request;
                }
		else if (is_array($request))
                {
			foreach ($request as $req)
				$this->add($req);
		}
                else
                        throw new RollingCurlGroupException("add: Request needs to be of instance RollingCurlGroupRequest");

		return true;
        }

        function addToRC($rc)
        {
		$ret = true;

                if (!($rc instanceof RollingCurl))
                        throw new RollingCurlGroupException("addToRC: RC needs to be of instance RollingCurl");

                while (count($this->requests) > 0)
		{
			$ret1 = $rc->add(array_shift($this->requests));
			if (!$ret1)
				$ret = false;
		}

		return $ret;
        }

        function process($output, $info, $request)
        {
                $this->finished_requests++;

                if ($this->finished_requests >= $this->num_requests)
                        $this->finished();
        }

        function finished()
        {
        }

}

class GroupRollingCurl extends RollingCurl {

	private $group_callback = null;

	protected function process($output, $info, $request)
	{
		if( $request instanceof RollingCurlGroupRequest)
			$request->process($output, $info);

		if (is_callable($this->group_callback))
			call_user_func($this->group_callback, $output, $info, $request);
	}

	function __construct($callback = null)
	{
		$this->group_callback = $callback;

		parent::__construct(array(&$this, "process"));
	}

	public function add($request) 
	{
		if ($request instanceof RollingCurlGroup)
			return $request->addToRC($this);
		else
			return parent::add($request);
	}

	public function execute($window_size = null) {

		if (count($this->requests) == 0)
			return false;

		return parent::execute($window_size);
	}

}

