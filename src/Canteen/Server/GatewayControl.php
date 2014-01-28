<?php

/**
*  @module Canteen\Server
*/
namespace Canteen\Server
{
	use \ReflectionClass;

	class GatewayControl
	{
		/**
		*  The local path on the gateway to access this callable
		*  @property {String} uri
		*/
		public $uri;

		/**
		*  The privilege needed to call this method
		*  @property {int} privilege
		*/
		public $privilege;

		/**
		*  The callable method when gateway access this
		*  @property {callable} call
		*/
		public $call;

		/**
		*  The total possible number of parameters for this call
		*  @property {int} total
		*/
		public $total;
		
		/**
		*  The required number of parameters for this call
		*  @property {int} required
		*/
		public $required;

		/**
		*  Gateway access control point. These are created internally by
		*  the Gateway class and shouldn't be created directly.
		*  @class GatewayControl
		*  @constructor
		*  @param {String} uri The URI path
		*  @param {callable} call The callback method
		*  @param {int} privilege The name of the privilege
		*/
		public function __construct($uri, $call, $privilege)
		{
			$this->uri = $uri;
			$this->call = $call;
			$this->privilege = $privilege;

			$reflector = new ReflectionClass(get_class($call[0]));
			$method = $reflector->getMethod($call[1]);
			$this->required = $method->getNumberOfRequiredParameters();
			$this->total = $method->getNumberOfParameters();
		}

		/**
		*  Check if the request being made belongs to this request
		*  @method match
		*  @param {String} request The URI being requested from the gateway
		*  @return {Boolean} If the request is matched
		*/
		public function match($request)
		{
			return ($request == $this->uri || strpos($request, $this->uri.'/') === 0);
		}

		/**
		*  Get the additional arguments from the URI
		*  @method getArguments
		*  @param {String} request The URI being requested from the gateway
		*  @return {Array} The arguments from the request
		*/
		public function getArguments($request)
		{
			if ($request == $this->uri) return [];
			$args = explode('/', str_replace($this->uri.'/', '', $request));
			return $args;
		}
	}	
}