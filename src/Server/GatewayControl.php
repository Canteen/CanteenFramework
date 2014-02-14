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
		*  The privilege needed to call this method
		*  @property {int} privilege
		*/
		public $privilege;

		/**
		*  The method name to call
		*  @property {String} call
		*/
		public $call;

		/**
		*  The callable method when gateway access this
		*  @property {callable} handler
		*/
		public $handler;

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
		*  @param {String} service The service name
		*  @param {String} call The call name
		*  @param {callable} handler The callback method
		*  @param {int} privilege The name of the privilege
		*/
		public function __construct($call, $handler, $privilege)
		{
			$this->call = $call;
			$this->handler = $handler;
			$this->privilege = $privilege;
			$reflector = new ReflectionClass(get_class($handler[0]));
			$method = $reflector->getMethod($handler[1]);
			$this->required = $method->getNumberOfRequiredParameters();
			$this->total = $method->getNumberOfParameters();
		}

		/**
		*  Get the additional arguments from the URI
		*  @method getArguments
		*  @param {String} request The URI being requested from the gateway
		*  @return {Array} The arguments from the request
		*/
		public function getArguments($request)
		{
			if ($request == $this->call) return [];
			$args = explode('/', str_replace($this->call.'/', '', $request));
			return $args;
		}
	}	
}