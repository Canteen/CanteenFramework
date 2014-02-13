<?php

/**
*  @module Canteen\Utilities
*/
namespace Canteen\Utilities
{
	/**
	*  Simple utilities for formatting strings. Located in the namespace __Canteen\Utilities__.
	*  
	*  @class StringUtils
	*/
	class StringUtils
	{
		/**
		*  Convert a uri (eg. get-all-users) to method call (e.g. getAllUsers)
		*  @method uriToMethodCall 
		*  @static
		*  @param {String} uri The input uri (lowercase, hyphen separated)
		*  @return {String} The method call name (lower, camel-cased)
		*/
		public static function uriToMethodCall($uri)
		{
			if (preg_match('/^[a-z]+\-[a-z\-]+$/', $uri))
			{
				$parts = explode('-', $uri);
				
				for($i = 1; $i < count($parts); $i++)
				{
					$parts[$i] = ucfirst($parts[$i]);
				}
				return implode('', $parts);
			}
			return $uri;
		}
		
		/**
		*  Convert a property name (myProperty) to a readable name (My Property)
		*  @method propertyToReadable
		*  @static
		*  @param {String} property The name of the property (lower camel-case)
		*  @return {String} The readable name, title-case
		*/
		public static function propertyToReadable($property)
		{
			return ucfirst(preg_replace('/([a-z])([A-Z])/', '$1 $2', $property));
		}
		
		/**
		*  Convert a property name (someProperty to const SOME_PROPERTY)
		*  @method convertPropertyToConst
		*  @static
		*  @param {String} property The property name
		*  @return {String} The property name as a constant name format
		*/
		public static function convertPropertyToConst($property)
		{
			return strtoupper(preg_replace('/([a-z])([A-Z])/', '$1_$2', $property));
		}
		
		/**
		*  Test a string to see if it's a regular expression
		*  @method isRegex
		*  @static 
		*  @param {String} str the string to test
		*  @return {Boolean} If the string is a regular expression
		*/
		public static function isRegex($str)
		{
			return (bool)preg_match('/^\/[\s\S]+\/$/', $str);
		}
		
		/**
		*  Get a random string of characters (readable-ish format)
		*  @method generateRandomString
		*  @static
		*  @param {int} [length=8] The length of the string to export
		*  @return {String} The string of random characters
		*/
		public static function generateRandomString($length=8) 
		{
			srand ( ( double ) microtime () * 1000000 ); 

			$password = '';
			$vowels = ['a','e','i','o','u']; 
			$cons = [
				'b','c','d','g','h','j','k','l','m','n','p','r','s','t','u','v','w','tr', 
				'cr','br','fr','th','dr','ch','ph','wr','st','sp','sw','pr','sl','cl'
			]; 

			$num_vowels = count($vowels); 
			$num_cons = count($cons); 

			for($i = 0; $i < $length; $i++)
			{ 
				$password .= $cons [ rand ( 0, $num_cons - 1 ) ] . $vowels [ rand ( 0, $num_vowels - 1 ) ]; 
			}
			return substr($password, 0, $length); 
		}
		
		/**
		*  Makes sure to include the trailing slash for a directory path
		*  @method requireSeparator
		*  @static
		*  @param {String} directory The directory path
		*  @return {String} The directory path with included trailing slash
		*/
		public static function requireSeparator($directory)
		{
			$last = substr($directory, -1);
			if ($last != DIRECTORY_SEPARATOR)
			{
				$directory .= DIRECTORY_SEPARATOR;
			}
			return $directory;
		}
		
		/**
		*  Check to see if a string is serialized, if it is return result
		*  @method isSerialized
		*  @static
		*  @author		Chris Smith <code+php@chris.cs278.org>
	 	*  @copyright	Copyright (c) 2009 Chris Smith (http://www.cs278.org/)
		*  @license		http://sam.zoy.org/wtfpl/ WTFPL
		*  @param {String} value Value to test for serialized form
		*  @param {mixed} [result=null] Result of unserialize() of the $value
		*  @return {Boolean} True if value is serialized data, otherwise false
		*/
		public static function isSerialized($value, &$result = null)
		{
			// Bit of a give away this one
			if (!is_string($value))
			{
				return false;
			}

			// Serialized false, return true. unserialize() returns false on an
			// invalid string or it could return false if the string is serialized
			// false, eliminate that possibility.
			if ($value === 'b:0;')
			{
				$result = false;
				return true;
			}

			$length	= strlen($value);
			$end	= '';

			switch ($value[0])
			{
				case 's':
					if ($value[$length - 2] !== '"')
					{
						return false;
					}
				case 'b':
				case 'i':
				case 'd':
					// This looks odd but it is quicker than isset()ing
					$end .= ';';
				case 'a':
				case 'O':
					$end .= '}';

					if ($value[1] !== ':')
					{
						return false;
					}

					switch ($value[2])
					{
						case 0:
						case 1:
						case 2:
						case 3:
						case 4:
						case 5:
						case 6:
						case 7:
						case 8:
						case 9:
						break;

						default:
							return false;
					}
				case 'N':
					$end .= ';';

					if ($value[$length - 1] !== $end[0])
					{
						return false;
					}
				break;

				default:
					return false;
			}

			if (($result = @unserialize($value)) === false)
			{
				$result = null;
				return false;
			}
			return true;
		}
		
		/**
		*  Works like in_array but uses native fnmatch
		*  @method fnmatchInArray 
		*  @static
		*  @param {String} needle The string to test
		*  @param {Array} haystack The haystack of items
		*  @return {Boolean} If the string is found in the array
		*/
		public static function fnmatchInArray($needle, $haystack)
		{
			foreach($haystack as $pattern)
			{
				if (fnmatch($pattern, $needle)) return true;
			}
			return false;
		}
		
		/**
		*  Replace the first occurrence in a string
		*  @method replaceOnce
		*  @static
		*  @param {String} search The string to search
		*  @param {String} replace The string to replace with
		*  @param {String} subject The string to replace on
		*  @return {String} The result after replacement
		*/
		public static function replaceOnce($search, $replace, $subject)
		{
			// Looks for the first occurence of $needle in $haystack
			// and replaces it with $replace.
			$pos = strpos($subject, $search);
			if ($pos === false) 
			{
				return $subject; // Nothing found
			}
			return substr_replace($subject, $replace, $pos, strlen($search));
		}
		
		/**
		*  Used to remove extra whitespace from a buffer
		*  Respects the textareas
		*  @method minify
		*  @static
		*  @param {String} buffer The output buffer
		*  @return {String} The minified HTML string, remove extra whitespace and returns
		*/
		public static function minify($buffer)
		{
			self::checkBacktrackLimit($buffer);
			
			// search for the textareas or tags that need newlines
			preg_match_all('/\<(textarea|pre)[^\>]*( \/\>|\>.*?\<\/\1\>)/s', $buffer, $matches);
			
			if ( count($matches[0]) )
			{
				// Remove textarea, pre elements
				foreach ($matches[0] as $i=>$m)
					$buffer = StringUtils::replaceOnce($m, "<pre$i>", $buffer);

				// Remove new line characters and tabs
				$buffer = self::stripBuffer($buffer);

				// Reinsert textarea, pre elements
				foreach ($matches[0] as $i=>$m)
					$buffer = StringUtils::replaceOnce("<pre$i>", $m, $buffer);
			}
			else
			{
				$buffer = self::stripBuffer($buffer);
			}
			return $buffer;
		}
		
		/**
		*  Strip new lines, tabs and extra spaces from a string
		*  @method stripBuffer
		*  @private
		*  @static
		*  @param {String} buffer The string buffer
		*  @return {String} The stripped string
		*/
		private static function stripBuffer($buffer)
		{
			$search = [
				'/\>[^\S ]+/s', //strip whitespaces after tags, except space
				'/[^\S ]+\</s', //strip whitespaces before tags, except space
				'/(\s)+/s'  // shorten multiple whitespace sequences
			];
			$replace = [
				'>',
				'<',
				'\\1'
			];
			self::checkBacktrackLimit($buffer);
			return preg_replace($search, $replace, $buffer);
		}
		
		/**
		*  The default backtrack limit for preg expressions is 100KB, 
		*  we may have pages which ar larger than 100,000, and 
		*  need to increase the pcre.backtrack_limit
		*  @method checkBacktrackLimit
		*  @static
		*  @param {String} string The string to limit test
		*/
		public static function checkBacktrackLimit($string)
		{
			$defaultLimit = ini_get('pcre.backtrack_limit');
			$length = strlen($string);
			if ($length > $defaultLimit)
			{
				ini_set('pcre.backtrack_limit', $length);
			}
		}
		
		/**
		*  Convert multiline text to be displayed within a textarea for editting
		*  @method multilineEdit
		*  @static
		*  @param {String} content The content to update
		*  @return {String} The editable content
		*/
		public static function multilineEdit(&$content)
		{
			$content = preg_replace('/\<br ?\/?\>/', '', $content);
			return $content;
		}
		
		/**
		*  Convert multiline text to be saved to the database for markup display
		*  @method multilineSave
		*  @static
		*  @param {String} content The content to save
		*  @return {String} The editable content
		*/
		public static function multilineSave(&$content)
		{
			$content = preg_replace('/\n/', '$0<br>', preg_replace('/\r/', '', $content));
			return $content;
		}
	}
}