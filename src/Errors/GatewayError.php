<?php

/**
*  @module Canteen\Errors
*/
namespace Canteen\Errors
{	
	/**
	*  Exceptions specific to the JSON server.  
	*  Located in the namespace __Canteen\Errors__.
	*  
	*  @class GatewayError
	*  @extends CanteenError
	*  @constructor
	*  @param {int} code The error code
	*  @param {String|Array} [data=''] The optional data associated with this error
	*/
	class GatewayError extends CanteenError
	{
		/** 
		*  No input provided
		*  @property {int} NO_INPUT
		*  @static
		*  @final
		*/
		const NO_INPUT = 400;

		/** 
		*  The wrong number of parameters 
		*  @property {int} INCORRECT_PARAMETERS
		*  @static
		*  @final
		*/
		const INCORRECT_PARAMETERS = 401;

		/** 
		*  Method can't be access by the client
		*  @property {int} INSUFFICIENT_PRIVILEGE
		*  @static
		*  @final
		*/
		const INSUFFICIENT_PRIVILEGE = 402;

		/** 
		*  The alias is already registerd
		*  @property {int} REGISTERED_URI
		*  @static
		*  @final
		*/
		const REGISTERED_URI = 403;

		/** 
		*  Bad URI request was made with the gateway
		*  @property {int} BAD_URI_REQUEST
		*  @static
		*  @final
		*/
		const BAD_URI_REQUEST = 404;

		/** 
		*  No control was found matching the URI request
		*  @property {int} NO_CONTROL_FOUND
		*  @static
		*  @final
		*/
		const NO_CONTROL_FOUND = 405;
		
		/**
		*  The collection of messages
		*  @property {Array} messages
		*  @private
		*  @static
		*  @final
		*/
		private static $messages = [
			self::NO_INPUT => 'No call made to gateway',
			self::INCORRECT_PARAMETERS => 'Incorrect parameter count',
			self::INSUFFICIENT_PRIVILEGE => 'Insufficient privilege needed to access this call \'%s\'',
			self::REGISTERED_URI => 'The gateway control \'%s\' is already registered',
			self::BAD_URI_REQUEST => 'The URI Request \'%s\' cannot be owned with the Gateway \'%s\'',
			self::NO_CONTROL_FOUND => 'No gateway call was found matching \'%s\' URI request'
		];
		
		/**
		*  Create a user error
		*/
		public function __construct($code, $data='')
		{
			parent::__construct($code, $data, self::$messages);
		}
	}
}