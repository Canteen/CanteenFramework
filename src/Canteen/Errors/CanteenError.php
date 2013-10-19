<?php

/**
*  @module Canteen\Errors
*/
namespace Canteen\Errors
{
	use \Exception;
	
	/**
	*  Canteen errors are exceptions which are only visible
	*  in debug mode. These are site critical things like
	*  setup is wrong. Located in the namespace __Canteen\Errors__.
	*  
	*  @class CanteenError
	*  @extends Exception
	*  @constructor
	*  @param {int} code The code number
	*  @param {String|Array} [data=''] Any extra data associated with error
	*  @param {Dictionary} [messages=null] The collection of messages to lookup
	*/
	class CanteenError extends Exception
	{
		/** 
		*  The method being called is for internal use only 
		*  @property {int} INTERNAL_ONLY
		*  @static
		*  @final
		*/
		const INTERNAL_ONLY = 100;
		
		/** 
		*  A particular data name is referenced but doesn exist 
		*  @property {int} INVALID_DATA
		*  @static
		*  @final
		*/
		const INVALID_DATA = 101;
		
		/** 
		*  Trying to access service($alias) and nothing can be created 
		*  @property {int} INVALID_SERVICE_ALIAS
		*  @static
		*  @final
		*/
		const INVALID_SERVICE_ALIAS = 102;
		
		/** 
		*  Bad controller implementation 
		*  @property {int} OVERRIDE_CONTROLLER_PROCESS
		*  @static
		*  @final
		*/
		const OVERRIDE_CONTROLLER_PROCESS = 104;
		
		/** 
		*  The form inheritance is wrong 
		*  @property {int} FORM_INHERITANCE
		*  @static
		*  @final
		*/
		const FORM_INHERITANCE = 105;
		
		/** 
		*  The form specified does not exist 
		*  @property {int} INVALID_FORM
		*  @static
		*  @final
		*/
		const INVALID_FORM = 106;
		
		/** 
		*  The settings are required for setup 
		*  @property {int} SETTINGS_REQUIRED
		*  @static
		*  @final
		*/
		const SETTINGS_REQUIRED = 107;
		
		/** 
		*  Submitted from the wrong domain 
		*  @property {int} WRONG_DOMAIN
		*  @static
		*  @final
		*/
		const WRONG_DOMAIN = 109;
		
		/** 
		*  The parse template can't be found 
		*  @property {int} TEMPLATE_NOT_FOUND
		*  @static
		*  @final
		*/
		const TEMPLATE_NOT_FOUND = 110;
		
		/** 
		*  Canteen version is unsufficient 
		*  @property {int} INSUFFICIENT_VERSION
		*  @static
		*  @final
		*/
		const INSUFFICIENT_VERSION = 111;
		
		/** 
		*  PHP version check failed 
		*  @property {int} INSUFFICIENT_PHP
		*  @static
		*  @final
		*/
		const INSUFFICIENT_PHP = 112;
		
		/** 
		*  The specified site index doesn't exist 
		*  @property {int} INVALID_INDEX
		*  @static
		*  @final
		*/
		const INVALID_INDEX = 113;
		
		/** 
		*  The settings file is invalid 
		*  @property {int} JSON_INVALID
		*  @static
		*  @final
		*/
		const JSON_INVALID = 114;
		
		/** 
		*  There was a problem decoding the JSON 
		*  @property {int} JSON_DECODE
		*  @static
		*  @final
		*/
		const JSON_DECODE = 115;
		
		/** 
		*  Couldn't make the cache folder 
		*  @property {int} CACHE_FOLDER
		*  @static
		*  @final
		*/
		const CACHE_FOLDER = 116;
		
		/** 
		*  The error if the cache dir isn't writable 
		*  @property {int} CACHE_FOLDER_WRITEABLE
		*  @static
		*  @final
		*/
		const CACHE_FOLDER_WRITEABLE = 117;
		
		/** 
		*  Duplicate named autoload class 
		*  @property {int} AUTOLOAD_CLASS
		*  @static
		*  @final
		*/
		const AUTOLOAD_CLASS = 118;
		
		/** 
		*  Duplicate named autoload template 
		*  @property {int} AUTOLOAD_TEMPLATE
		*  @static
		*  @final
		*/
		const AUTOLOAD_TEMPLATE = 119;
		
		/** 
		*  Duplicate named service alias
		*  @property {int} TAKEN_SERVICE_ALIAS
		*  @static
		*  @final
		*/
		const TAKEN_SERVICE_ALIAS = 120;
		
		/** 
		*  The template alias is wrong
		*  @property {int} TEMPLATE_UNKNOWN
		*  @static
		*  @final
		*/
		const TEMPLATE_UNKNOWN = 121;
		
		/**
		*  The collection of messages
		*  @property {Array} messages
		*  @private
		*  @static
		*  @final
		*/
		private static $messages = array(
			self::INTERNAL_ONLY => 'Method is only accessible internally',
			self::INVALID_DATA => 'The data property does not exist',
			self::INVALID_SERVICE_ALIAS => 'The alias or class of the service does not exist',
			self::OVERRIDE_CONTROLLER_PROCESS => "Controller must override 'process' method",
			self::FORM_INHERITANCE => 'Form is not an instance of the Form class',
			self::INVALID_FORM => 'Form does not exist',
			self::JSON_INVALID => 'File must be a valid JSON file',
			self::JSON_DECODE => 'Failure decoding JSON',
			self::SETTINGS_REQUIRED => 'Setup file must contain an array of dictionary objects.',
			self::WRONG_DOMAIN => 'Form submitted from the wrong domain',
			self::TEMPLATE_NOT_FOUND => 'Cannot load template file',
			self::TEMPLATE_UNKNOWN => 'Template not registered',
			self::INSUFFICIENT_VERSION => 'The current version of Canteen Site is insufficient to run site',
			self::INSUFFICIENT_PHP => 'The current version of PHP is insufficient to run site',
			self::INVALID_INDEX => 'The index page for the site does not exist',
			self::CACHE_FOLDER => 'Could not create the cache folder',
			self::CACHE_FOLDER_WRITEABLE => 'Cache folder is not writable. Change file permissions',
			self::AUTOLOAD_CLASS => 'Class has already been loaded',
			self::AUTOLOAD_TEMPLATE => 'Template has already been loaded',
			self::TAKEN_SERVICE_ALIAS => 'The custom service alias is already taken'
		);
		
		/** 
		*  The label for an error that is unknown or unfound in messages 
		*  @property {int} UNKNOWN
		*  @static
		*  @final
		*/
		const UNKNOWN = 'Unknown error';
		
		/**
		*  Create the Canteen error
		*/
		public function __construct($code, $data='', $messages=null)
		{
			$messages = ifsetor($messages, self::$messages);
			$message =  ifsetor($messages[$code], self::UNKNOWN);
			
			// If the string contains substitution strings
			// we should apply the subs
			if (preg_match('/\%s/', $message))
			{
				$args = array_merge(array($message), is_array($data) ? $data : array($data));
				$message = call_user_func_array('sprintf', $args);
			}
			// Just add the extra data at the end of the message
			else if (!empty($data))
			{
				$message .= ' : ' . $data;	
			}	
			parent::__construct($message, $code);
		}
	
		/**
		*  Get the result object
		*  @method getResult
		*  @return {Dictionary} The result Exception object formatted nicely
		*/
		public function getResult()
		{
			return self::convertToResult($this);
		}
		
		/**
		*  A utility function to convert any exception to formatted result
		*  @method convertToResult
		*  @static
		*  @param {Exception} e The exception to convert to better formatted result
		*  @return {Dictionary} The result Exception object formatted nicely
		*/
		public static function convertToResult(Exception $e)
		{
			return array(
				'message' => $e->getMessage(),
				'file' => str_replace(CANTEEN_PATH, '', $e->getFile())." (line:{$e->getLine()})",
				'code' => $e->getCode(),
				'stackTrace' => self::getFormattedTrace($e)
			);
		}
		
		/**
		*  A utility function to formatted the exception stack trace
		*  @method getFormattedTrace
		*  @protected
		*  @static
		*  @param {Exception} e The exception to convert to trace
		*/
		protected static function getFormattedTrace(Exception $e)
		{
			$trace = $e->getTraceAsString();
			$trace = preg_split('/\#[0-9]+ /', $trace);
			$stack = array();
			foreach($trace as $t)
			{
				$t = trim($t);
				if (!$t) continue;
				$stack[] = str_replace(CANTEEN_PATH, '', $t);
			}
			return $stack;
		}
	}
}