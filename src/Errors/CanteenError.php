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
		*  The source directory path to Canteen folder, this is used
		*  to remove the verbose path to Canteen 
		*  @property {String} rootPath 
		*  @static
		*/
		public static $rootPath = '';
		
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
		const INVALID_SETTING = 101;
		
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
		*  No settings were found for a matching domain
		*  @property {int} NO_SETTINGS
		*  @static
		*  @final
		*/
		const NO_SETTINGS = 125;
		
		/** 
		*  Submitted from the wrong domain 
		*  @property {int} WRONG_DOMAIN
		*  @static
		*  @final
		*/
		const WRONG_DOMAIN = 109;
		
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
		*  Duplicate named autoload class 
		*  @property {int} AUTOLOAD_CLASS
		*  @static
		*  @final
		*/
		const AUTOLOAD_CLASS = 118;
		
		/** 
		*  Duplicate named service alias
		*  @property {int} TAKEN_SERVICE_ALIAS
		*  @static
		*  @final
		*/
		const TAKEN_SERVICE_ALIAS = 120;
		
		/** 
		*  Unable to change the setting
		*  @property {int} SETTING_WRITEABLE
		*  @static
		*  @final
		*/
		const SETTING_WRITEABLE = 122;
		
		/** 
		*  Unable to delete the setting
		*  @property {int} SETTING_DELETE
		*  @static
		*  @final
		*/
		const SETTING_DELETE = 123;
		
		/** 
		*  The setting name is already taken
		*  @property {int} SETTING_NAME_TAKEN
		*  @static
		*  @final
		*/
		const SETTING_NAME_TAKEN = 124;

		/** 
		*  The class doesn't exist
		*  @property {int} INVALID_CLASS
		*  @static
		*  @final
		*/
		const INVALID_CLASS = 127;

		/** 
		*  Property doesn't exist on the class
		*  @property {int} INVALID_PROPERTY
		*  @static
		*  @final
		*/
		const INVALID_PROPERTY = 126;
		
		/**
		*  The collection of messages
		*  @property {Array} messages
		*  @private
		*  @static
		*  @final
		*/
		private static $messages = [
			self::SETTING_DELETE => 'The setting \'%s\' cannot be deleted',
			self::SETTING_WRITEABLE => 'The setting \'%s\' cannot be changed',
			self::SETTING_NAME_TAKEN => 'The setting name \'%s\' is taken, please rename',
			self::INTERNAL_ONLY => 'Method \'%s\' is only accessible internally from the following classes: %s',
			self::INVALID_SETTING => 'The setting \'%s\' property does not exist',
			self::INVALID_SERVICE_ALIAS => 'The alias or class of the service does not exist',
			self::OVERRIDE_CONTROLLER_PROCESS => "Controller must override 'process' method",
			self::FORM_INHERITANCE => 'Form is not an instance of the Form class',
			self::INVALID_FORM => 'Form does not exist',
			self::SETTINGS_REQUIRED => 'Setup file must contain an array of dictionary objects.',
			self::NO_SETTINGS => 'No settings were found for this domain (%s) please update your config file',
			self::WRONG_DOMAIN => 'Form submitted from the wrong domain',
			self::INSUFFICIENT_VERSION => 'The installed version of Canteen Site (%s) is insufficient to run site (%s)',
			self::INSUFFICIENT_PHP => 'The current version of PHP (%s) is insufficient to run site (%s)',
			self::INVALID_INDEX => 'The index page for the site does not exist',
			self::AUTOLOAD_CLASS => 'Class has already been loaded',
			self::TAKEN_SERVICE_ALIAS => 'The custom service alias is already taken',
			self::INVALID_CLASS => 'The class \'%s\' doesn\'t exist',
			self::INVALID_PROPERTY => 'The property "%s" doest not exist on this class %s'
		];
		
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
				$args = array_merge(array($message), is_array($data) ? $data : [$data]);
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
			return [
				'message' => $e->getMessage(),
				'file' => str_replace(self::$rootPath, '', $e->getFile())." (line:{$e->getLine()})",
				'code' => $e->getCode(),
				'stackTrace' => self::getFormattedTrace($e)
			];
		}
		
		/**
		*  A utility function to formatted the exception stack trace
		*  @method getFormattedTrace
		*  @protected
		*  @static
		*  @param {Exception} e The exception to convert to trace
		*  @return {Array} The collection of arrays
		*/
		protected static function getFormattedTrace(Exception $e)
		{
			$trace = $e->getTraceAsString();
			$trace = preg_split('/\#[0-9]+ /', $trace);
			$stack = [];
			foreach($trace as $t)
			{
				$t = trim($t);
				if (!$t) continue;
				$stack[] = str_replace(self::$rootPath, '', $t);
			}
			return $stack;
		}
	}
	
	CanteenError::$rootPath = dirname(__DIR__).'/';
}