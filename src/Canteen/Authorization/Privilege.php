<?php

/**
*  @module Canteen\Authorization
*/
namespace Canteen\Authorization
{
	/**
	*  Reference for the different user privileges. 
	*  Located in the namespace __Canteen\Authorization__.
	*  
	*  @class Privilege
	*/
	class Privilege
	{
		/** 
		*  No access 
		*  @property {int} ANONYMOUS
		*  @final
		*  @static
		*/
		const ANONYMOUS = 0;
		
		/** 
		*  An authorized user ability to see but not to change anything 
		*  @property {int} GUEST
		*  @final
		*  @static
		*/
		const GUEST = 1;
		
		/** 
		*  An authorized user ability to see but not to change anything 
		*  @property {int} SUBSCRIBER
		*  @final
		*  @static
		*/
		const SUBSCRIBER = 2;
		
		/** 
		*  An authorized user, basic access to make changes 
		*  @property {int} CONTRIBUTOR
		*  @final
		*  @static
		*/
		const CONTRIBUTOR = 3;
		
		/** 
		*  Ability to make editorial content changes 
		*  @property {int} EDITOR
		*  @final
		*  @static
		*/
		const EDITOR = 4;
		
		/** 
		*  Ability to manage all site options, user, pages and configuration
		*  @property {int} ADMINISTRATOR
		*  @final
		*  @static
		*/
		const ADMINISTRATOR = 5;
		
		/**
		*  Get a list of all of the privileges
		*  @method getAll
		*  @static
		*  @return {Array} A collection of al privileges (privilege => label)
		*/
		public static function getAll()
		{
			return [
				self::ANONYMOUS => 'Anonymous',
				self::GUEST => 'Guest',
				self::SUBSCRIBER => 'Subscriber',
				self::CONTRIBUTOR => 'Contributor',
				self::EDITOR => 'Editor',
				self::ADMINISTRATOR => 'Administrator'
			];
		}
	}
}