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
	*  @class JSONServerError
	*  @extends CanteenError
	*  @constructor
	*  @param {int} code The error code
	*  @param {String|Array} [data=''] The optional data associated with this error
	*/
	class JSONServerError extends CanteenError
	{
		/** 
		*  Error service couldn't be located 
		*  @property {int} INVALID_SERVICE
		*  @static
		*  @final
		*/
		const INVALID_SERVICE = 400;
		
		/** 
		*  Service doesn't extend Service class 
		*  @property {int} SERVICE_ERROR
		*  @static
		*  @final
		*/
		const SERVICE_ERROR = 401;
		
		/** 
		*  The method name is invalid 
		*  @property {int} INVALID_METHOD
		*  @static
		*  @final
		*/
		const INVALID_METHOD = 402;
		
		/** 
		*  The wrong number of parameters 
		*  @property {int} INCORRECT_PARAMETERS
		*  @static
		*  @final
		*/
		const INCORRECT_PARAMETERS = 403;
		
		/**
		*  The collection of messages
		*  @property {Array} messages
		*  @private
		*  @static
		*  @final
		*/
		private static $messages = array(
			self::INVALID_SERVICE => 'No valid service found',
			self::SERVICE_ERROR => 'Invalid service',
			self::INVALID_METHOD => 'No valid method found',
			self::INCORRECT_PARAMETERS => 'Incorrect parameter count'
		);
		
		/**
		*  Create a user error
		*/
		public function __construct($code, $data='')
		{
			parent::__construct($code, $data, self::$messages);
		}
	}
}