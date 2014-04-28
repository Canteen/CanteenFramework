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
		*  The router pattern
		*  @property {String} pattern
		*/
		public $pattern;

		/**
		*  The route for the router
		*  @property {String} route
		*/
		public $route;

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
		*  @param {String} pattern The pattern route call
		*  @param {callable} handler The callback method
		*  @param {int} privilege The name of the privilege
		*/
		public function __construct($pattern, $handler, $privilege)
		{
			$this->pattern = $pattern;
			$this->handler = $handler;
			$this->privilege = $privilege;
			$reflector = new ReflectionClass(get_class($handler[0]));
			$method = $reflector->getMethod($handler[1]);
			$this->required = $method->getNumberOfRequiredParameters();
			$this->total = $method->getNumberOfParameters();
		}
	}	
}