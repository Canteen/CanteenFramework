<?php

/**
*  @module Canteen\Utilities
*/
namespace Canteen\Utilities
{
	use Canteen\Site;

	/**
	*  The collection of date related utilities
	*  @class DateUtils
	*/
	class DateUtils
	{
		/**
		*  Add date display to multiple objects or collection of objects. Can be 
		*  run recursively if there are nested objects or arrays.
		*  @method addDisplay
		*  @static
		*  @param {Array} object The collection of objects
		*  @param {String} [key='created'] The data key to update
		*  @return {Array} The updated objects collection
		*/
		static public function addDisplay(&$objects, $key='created', $recursive=false)
		{
			if (is_array($objects))
			{
				foreach($objects as $i=>$object)
				{
					self::addDisplay($object, $key, $recursive);
				}
			}
			else if (is_object($objects))
			{
				if (!is_array($key)) $key = [$key];

				foreach($key as $k)
				{
					if (property_exists($objects, $k))
					{
						$objects->$k = self::display($objects->$k);
					}
				}			

				if ($recursive)
				{
					$vars = get_object_vars($objects);

					foreach ($vars as $value)
					{
						if (is_array($value) || is_object($value))
						{
							self::addDisplay($value, $key, $recursive);
						}
					}
				}
			}
			return $objects;
		}

		/**
		*  The global date formatting.
		*  @method display
		*  @static
		*  @param {Date|int} date The string date or number of seconds
		*  @param {String} [format=null] The optional format
		*/
		static public function display(&$date)
		{
			$date = self::readable(strtotime($date));
			return $date;
		}

		/**
		*  Represent a timestamp as a human readable time
		*  @method readable
		*  @private
		*  @static
		*  @param {int} timestamp The number of seconds
		*  @return {String} The readable date
		*/
		static private function readable($timestamp)
		{
			$time = time() - $timestamp; // to get the time since that moment

		    $tokens = array (
		        31536000 => 'year',
		        2592000 => 'month',
		        604800 => 'week',
		        86400 => 'day',
		        3600 => 'hour',
		        60 => 'minute',
		        1 => 'second'
		    );

		    foreach ($tokens as $unit => $text)
		    {
		        if ($time < $unit) continue;
		        
		        $numberOfUnits = floor($time / $unit);
		        
		        return $numberOfUnits.' '.$text.(($numberOfUnits>1)?'s':''). ' ago';
		    }
		}

		/**
		*  Convert a date to mysql format
		*  @method toDatabase
		*  @static
		*  @param {String} time The input date format
		*  @return {String} The format suitable for database
		*/
		static public function toDatabase($time)
		{
			return date('Y-m-d H:i:s', strtotime($time));
		}
	}
}
