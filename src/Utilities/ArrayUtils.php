<?php

/**
*  @module Canteen\Utilities 
*/
namespace Canteen\Utilities
{
	/**
	*  The collection of utilities for interacting with PHP Arrays
	*  @class ArrayUtils
	*/
	class ArrayUtils
	{
		/**
		*  Check to see if an array is associative
		*  @method isAssoc
		*  @static
		*  @param {Array} arr The array to check
		*  @return {Boolean} if the array is associative
		*/
		public static function isAssoc($arr)
		{
		    return array_keys($arr) !== range(0, count($arr) - 1);
		}
	}
}
