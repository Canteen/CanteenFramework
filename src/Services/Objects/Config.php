<?php

/**
*  @module Canteen\Services\Objects
*/
namespace Canteen\Services\Objects
{
	/**
	*  Data class which represents a single config property.  Located in the namespace __Canteen\Services\Objects__.
	*  
	*  @class Config
	*/
	class Config
	{
		/** 
		*  The unique config id 
		*  @property {int} id
		*/
		public $id;
		
		/** 
		*  The config name 
		*  @property {String} name
		*/
		public $name;
		
		/** 
		*  The value of the config
		*  @property {mixed} value
		*/
		public $value;
		
		/** 
		*  Either "string" or "integer"
		*  @property {Sring} type
		*/
		public $type;
	}
}