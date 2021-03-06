<?php

/**
*  @module Canteen\Services
*/
namespace Canteen\Services
{	
	use Canteen\Authorization\Privilege;

	/**
	*  Service for getting the time off the server.  Located in the namespace __Canteen\Services__.
	*  
	*  @class TimeService
	*  @extends Service
	*/
	class TimeService extends Service
	{
		/**
		*  Create the service
		*/
		public function __construct()
		{
			$this->gateway('time/get-server-time', 'getServerTime');
		}
		
		/**
		*  Get the local server time when a more
		*  reliable time is needed than the client time
		*  @method getServerTime
		*  @return {int} The number of UNIX seconds since Jan. 1, 1970
		*/
		public function getServerTime()
		{
			return time();
		}
	}
}