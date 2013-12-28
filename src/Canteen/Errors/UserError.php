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
	*  @param {String|Array} [data=''] The optional data associated with this error
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
		*  The supplied data is invalidly formatted for MySQL
		*  @property {int} INVALID_MYSQL_DATE
		*  @static
		*  @final
		*/
		const INVALID_MYSQL_DATE = 205;
		
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
			self::INVALID_DATA_SET => '\'%s\' is not in the set [%s]',
			self::INVALID_DATA => '\'%s\' cannot contain [%s], only [%s]',
			self::INVALID_MYSQL_DATE => '\'%s\' is not a valid MySQL format (YYYY-MM-DD or YYYY-MM-DD HH:mm:ss)'
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