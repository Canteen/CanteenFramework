<?php

/**
*  @module Canteen\Errors
*/
namespace Canteen\Errors
{	
	/**
	*  Errors for saving to the file system.
	*  Located in the namespace __Canteen\Errors__.
	*  
	*  @class FileError
	*  @extends CanteenError
	*  @constructor
	*  @param {int} code The error code
	*  @param {String|Array} [data=''] The optional data associated with this error
	*/
	class FileError extends CanteenError
	{
		/** 
		*  Couldn't make the folder 
		*  @property {int} FOLDER_MAKE
		*  @static
		*  @final
		*/
		const FOLDER_CREATE = 116;
		
		/** 
		*  The error if the dir isn't writable 
		*  @property {int} FOLDER_WRITEABLE
		*  @static
		*  @final
		*/
		const FOLDER_WRITEABLE = 117;

		/** 
		*  The settings file is invalid 
		*  @property {int} JSON_INVALID
		*  @static
		*  @final
		*/
		const FILE_EXISTS = 114;
		
		/**
		*  The collection of messages
		*  @property {Array} messages
		*  @private
		*  @static
		*  @final
		*/
		private static $messages = [
			self::FOLDER_CREATE => 'Unable to create the folder (%s). Make the parent directory writable or manually create the folder with writeable permissions.',
			self::FOLDER_WRITEABLE => 'The folder (%s) is not writable. Change file permissions',
			self::FILE_EXISTS => 'Unable to read the file (%s). Check that it exists.',
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