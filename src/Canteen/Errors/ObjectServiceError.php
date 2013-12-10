<?php

/**
*  @module Canteen\Errors
*/
namespace Canteen\Errors
{	
	/**
	*  Exceptions specific to the ObjectService API.  
	*  Located in the namespace __Canteen\Errors__.
	*  
	*  @class ObjectServiceError
	*  @extends CanteenError
	*  @constructor
	*  @param {int} code The error code
	*  @param {String|Array} [data=''] The optional data associated with this error
	*/
	class ObjectServiceError extends CanteenError
	{
		/** 
		*  There is not a valid dynamic method matching this name
		*  @property {int} INVALID_INDEX
		*  @static
		*  @final
		*/
		const INVALID_INDEX = 600;
		
		/** 
		*  Custom service does not have a default index
		*  @property {int} NO_DEFAULT_INDEX
		*  @static
		*  @final
		*/
		const NO_DEFAULT_INDEX = 601;

		/** 
		*  The dynamic method name is invalid for the service
		*  @property {int} INVALID_METHOD
		*  @static
		*  @final
		*/
		const INVALID_METHOD = 602;
		
		/**
		*  The collection of messages
		*  @property {Array} messages
		*  @private
		*  @static
		*  @final
		*/
		private static $messages = array(
			self::INVALID_INDEX => 'There is no custom field index with the name "%s", set this field to be isIndex=true',
			self::NO_DEFAULT_INDEX => 'The custom service does not have a default index field, set isDefault=true on a field',
			self::INVALID_METHOD => 'The dynamic method call "%s" is not valid on this custom service'
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