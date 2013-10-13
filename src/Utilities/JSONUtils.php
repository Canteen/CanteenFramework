<?php

/**
*  @module Canteen\Utilities
*/
namespace Canteen\Utilities
{
	use Canteen\Errors\CanteenError;
	
	/**
	*  Utiltities for dealing with JSON files. Located in the namespace __Canteen\Utilities__.
	*  
	*  @class JSONUtils
	*/
	class JSONUtils
	{
		/**
		*  Load a JSON file from a path, does the error checking
		*  @method load
		*  @static
		*  @param {String} path The path to the .json file
		*  @param {Boolean} [asAssociate=true] Return as associative array
		*  @return {Array} The native object or array
		*/
		public static function load($path, $asAssociative=true)
		{
			if (!fnmatch('*.json', $path) || !file_exists($path))
			{
				throw new CanteenError(CanteenError::JSON_INVALID, $path);
			}
			
			$json = json_decode(file_get_contents($path), $asAssociative);
			
			if (empty($json))
			{
				throw new CanteenError(CanteenError::JSON_DECODE, self::lastJsonError());
			}
			return $json;
		}
		
		/**
		*  Get the last JSON error message
		*  @method lastJsonError
		*  @private
		*  @static
		*  @return {String} The json error message
		*/
		private static function lastJsonError()
		{
			// For PHP 5.5.0+
			if (function_exists('json_last_error_msg'))
			{
				return json_last_error_msg();
			}
			
			// If we can get the specific error, we should
			// Introduced in PHP 5.3.0
			if (function_exists('json_last_error'))
			{
				$errors = array(
					JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
					JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
					JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
					JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON'
				);
				// Introduced in PHP 5.3.3
				if (defined('JSON_ERROR_UTF8'))
				{
					$errors[JSON_ERROR_UTF8] = 'Malformed UTF-8 characters, possibly incorrectly encoded';
				}
				return ifsetor($errors[json_last_error()], '');
			}
			return '';
		}
	}
}