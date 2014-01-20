<?php

/**
*  @module Canteen\Utilities
*/
namespace Canteen\Utilities
{
	/**
	*  Utilities methods for handling objects
	*  @class ObjectUtils
	*/
	class ObjectUtils
	{
		/**
		*  Get the collection of unique ids on a collection of properties
		*  @method getUniqueValues
		*  @static
		*  @param {Array|Object} objects The collection of objects
		*  @param {String} field The name of the field to get unique vlues on
		*  @param {Boolean} [recursive=false] If we should look at object keys for addional
		* 		array or objects which might contain fields.
		*  @param {Array} [&values=null] The capture of values
		*  @return {Array} The unique values on an object field
		*/
		public static function uniqueValues($objects, $field, $recursive=false, &$values=null)
		{
			// Create is the new values collection
			if ($values == null) $values = [];

			// Look through the collection of objects
			if (is_array($objects))
			{
				foreach($objects as $o)
				{
					self::uniqueValues($o, $field, $recursive, $values);			
				}
				$values = array_unique($values);
			}
			// Add a single object
			else if (is_object($objects))
			{
				if (property_exists($objects, $field))
				{
					$values[] = $objects->$field;
				}

				// Search objects for additional arrays or object
				// which might have keys
				if ($recursive)
				{
					$vars = get_object_vars($objects);

					foreach($vars as $name=>$o)
					{
						if (is_array($o) || is_object($o))
						{
							self::uniqueValues($o, $field, $recursive, $values);
						}
					}
					$values = array_unique($values);
				}
			}
			return $values;
		}
	}
}