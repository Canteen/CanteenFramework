<?php

/**
*  @module Canteen\Utilities
*/
namespace Canteen\Utilities
{
	use Canteen\Errors\UserError;
	
	/**
	*  Utilities to help sanitize data. Located in the namespace __Canteen\Utilities__.
	*  
	*  @class Validate
	*/
	class Validate
	{
		/** 
		*  Validation Type: remove all non alpha numeric characters, includes a-z, A-Z, 0-9
		*  @property {RegExp} ALPHA_NUMERIC
		*  @final
		*  @static
		*/
		const ALPHA_NUMERIC = '/[a-zA-Z0-9]/';
		
		/** 
		*  Validation Type: remove all non numeric characters, includes 0-9
		*  @property {RegExp} NUMERIC
		*  @final
		*  @static
		*/
		const NUMERIC = '/[0-9]/';
		
		/** 
		*  Validation Type: booleans, includes 0-1
		*  @property {RegExp} BOOLEAN
		*  @final
		*  @static
		*/
		const BOOLEAN = '/[0-1]/';
		
		/** 
		*  Validation Type: remove all non decimal characters, includes 0-9, .
		*  @property {RegExp} DECIMAL
		*  @final
		*  @static
		*/
		const DECIMAL = '/[0-9\.]/';
		
		/** 
		*  Validation Type: remove all non alpha characters, includes a-z, A-Z
		*  @property {RegExp} ALPHA
		*  @final
		*  @static
		*/
		const ALPHA = '/[a-zA-Z]/';
		
		/** 
		*  Validation Type: for people's names, includes a-z, A-Z, -, .
		*  @property {RegExp} NAMES
		*  @final
		*  @static
		*/
		const NAMES = '/[a-zA-Z\-\'\.]/';
		
		/** 
		*  Validation type: for file names, includes a-z, A-Z, 0-9, -, _, .
		*  @property {RegExp} FILE_NAME
		*  @final
		*  @static
		*/
		const FILE_NAME = '/[a-zA-Z0-9\-\_\.]/';
		
		/** 
		*  Validation Type: remove all non alpha numeric punctuation characters
		*  @property {RegExp} FULL_TEXT
		*  @final
		*  @static
		*/
		const FULL_TEXT = '/[a-zA-Z0-9\%\?\'\"\/\\\.\,\:\;\-\_\=\+\#\!\&\@\{\}\(\)\|\[\]\* ]/';
				
		/** 
		*  Validation Type: remove all non standard uri characters, includes a-z, A-Z, 0-9, -, _, ., /
		*  @property {RegExp} URI
		*  @final
		*  @static
		*/
		const URI = '/[a-zA-Z0-9\-\_\/\.]/';
		
		/** 
		*  Validation Type: remove all non standard URL characters, includes a-z, A-Z, 0-9, -, _, ., /, &, %, :, =
		*  @property {RegExp} URL
		*  @final
		*  @static
		*/
		const URL = '/[a-zA-Z0-9\-\_\/\.\:\=\?\#\&\%\,\.]/';

		/** 
		*  Validation Type: remove all non search characters, includes a-z, A-Z, 0-9, space
		*  @property {RegExp} SEARCH
		*  @final
		*  @static
		*/
		const SEARCH = '/[a-zA-Z0-9 ]/';
		
		/** 
		*  Validation Type: remove all non email characters, includes a-z, A-Z, 0-9, ., -, _, @
		*  @property {RegExp} EMAIL
		*  @final
		*  @static
		*/
		const EMAIL = '/[a-zA-Z0-9\.\-\_\@]/';
		
		/** 
		*  The valid mysql date format 
		*  @property {RegExp} MYSQL_DATE
		*  @final
		*  @static
		*/
		const MYSQL_DATE = '/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}( [0-9]{2}\:[0-9]{2}\:[0-9]{2})?$/';
		
		/**
		*  Sanitize input data using the validation types above
		*  @method verify
		*  @static
		*  @param {String|Array} data The data to be validated, can be an array of items
		*  @param {RegExp} [type=null] The type of validation, defaults to Numeric. Can also be an array set of items
		*  @param {Boolean} [suppressErrors=false] If we should suppress throwing errors
		*  @return {mixed} If we don't verify and suppress errors, returns false, else returns the data
		*/
		public static function verify($data, $type=null, $suppressErrors=false)
		{
			// If the data is a collection, recursively validate each item
			if (is_array($data))
			{
				foreach($data as $i=>$d)
				{
					$result = self::verify($d, $type, $suppressErrors);
					if ($suppressErrors && $result === false)
					{
						return false;
					}
				}
				return $data;
			}
			// Validate a single data item
			else
			{
				// Limit to a set of acceptable values
				if ($type && is_array($type))
				{
					if (!in_array($data, $type))
					{						
						if (!$suppressErrors)
							throw new UserError(UserError::INVALID_DATA_SET, array($data, implode(', ', $type)));
							
						return false;
					}
					return $data;
				}
				
				// Check for string types
				if ($type == self::MYSQL_DATE)
				{
					if (!preg_match($type, $data))
					{
						if (!$suppressErrors)
							throw new UserError(UserError::INVALID_MYSQL_DATE, $data);
							
						return false;
					}
					return $data;
				}
								
				// Filter out valid characters and remain with the bad characters
				$badCharacters = preg_replace(($type === null ? self::NUMERIC : $type), '', $data);
				
				if (!empty($data) && strlen($badCharacters) && $data != 'null')
				{
					if (!$suppressErrors)
					{
						$restricted = '';
						
						// Get the list of unique bad characters
						if ($badCharacters)
						{
							$chars = '';
							foreach (count_chars($badCharacters, 1) as $i => $val)
							{
								$chars .= chr($i);
							}
						}
						throw new UserError(UserError::INVALID_DATA, array(
							$data, 
							$chars, 
							stripcslashes(substr($type, 2, -2)))
						);
					}						
					return false;
				}
				return $data;
			}
		}
		
		/**
		*  Verify multiple data with multiple types
		*  @method verifyMutli
		*  @static
		*  @param {Array} data The associative array of data e.g. array('name'=>"something", 'title'=>"another")
		*  @param {Array} types The associate array of types e.g. array('name'=>Validate::NAMES, 'title'=>Validate::FULL_TEXT)
		*  @param {Boolean} [suppressErrors=false] If we should suppress throwing errors
		*  @return {mixed} False if anything doesn't verify or else returns data
		*/
		public static function verifyMulti($data, $types, $suppressErrors=false)
		{
			foreach($data as $name=>$value)
			{
				$result = self::verify($value, ifestor($types[$name], null), $suppressErrors);
				if ($suppressErrors && $result === false)
				{
					return false;
				}
			}
			return $data;
		}
	}
}