<?php

/**
*  @module Canteen\Errors
*/
namespace Canteen\Errors
{
	/**
	*  A user-facing error, such as a form, privilege or validation error.  
	*  Located in the namespace __Canteen\Errors__.
	*  
	*  @class UserError
	*  @extends CanteenError
	*  @constructor
	*  @param {int} code The error code
	*  @param {String} [data=''] The optional data associated with this error
	*/
	class UserError extends CanteenError
	{
		/** 
		*  The user is logged out 
		*  @property {int} LOGGIN_REQUIRED
		*  @static
		*  @final
		*/
		const LOGGIN_REQUIRED = 200;
		
		/** 
		*  The user doesn't have sufficient privileges to perform action 
		*  @property {int} INSUFFICIENT_PRIVILEGE
		*  @static
		*  @final
		*/
		const INSUFFICIENT_PRIVILEGE = 201;
		
		/** 
		*  Validating a property against a set of options 
		*  @property {int} INVALID_DATA_SET
		*  @static
		*  @final
		*/
		const INVALID_DATA_SET = 202;
		
		/** 
		*  Validating a property data
		*  @property {int} INVALID_DATA
		*  @static
		*  @final
		*/
		const INVALID_DATA = 203;
		
		/** 
		*  The verify function check to see if data matches a specific regexp format 
		*  @property {int} INVALID_DATA_FORMAT
		*  @static
		*  @final
		*/
		const INVALID_DATA_FORMAT = 204;
		
		/**
		*  The collection of error messages
		*  @property {Dictionary} messages
		*  @private
		*  @static
		*  @final
		*/
		private static $messages = array(
			self::LOGGIN_REQUIRED => 'Login required',
			self::INSUFFICIENT_PRIVILEGE => 'Insufficient privilege required',
			self::INVALID_DATA_SET => 'Property is not a valid option',
			self::INVALID_DATA => 'The data did not validate',
			self::INVALID_DATA_FORMAT => 'The data does not match the intended format'
		);
		
		/**
		*  Create a user error
		*/
		public function __construct($code, $data='')
		{
			parent::__construct($code, $data, self::$messages);
		}
		
		/**
		*  Create a simple to string method since users will see theses
		*  @method __toString
		*  @return {String} The string representation of this Error
		*/
		public function __toString()
	    {
			return $this->getMessage();
		}
	}
}