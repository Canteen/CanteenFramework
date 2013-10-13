<?php

/**
*  @module Canteen\Utilities
*/
namespace Canteen\Utilities
{
	use Canteen\Site;
	use Canteen\Errors\CanteenError;
	use Canteen\Services\Service;
	
	/**
	*  Common functionality for Services, Controllers, Forms. Located in the namespace __Canteen\Utilities__.
	*  
	*  @class CanteenBase
	*/
	abstract class CanteenBase
	{		
		/**
		*  Get the config
		*  @method data
		*  @protected
		*  @param {String} [data=null] The name of the config property
		*  @return {String|Dictionary} Either value of the data or all items if param name is null
		*/
		protected function data($name=null)
		{
			$data = Site::instance()->getData($name);
			if ($data === null)
			{
				throw new CanteenError(CanteenError::INVALID_DATA, $name);
			}
			return $data;
		}
		
		/**
		*  Get a service by alias or classname
		*  @method service
		*  @protected
		*  @param {String} aliasOrClassName The alias or classname
		*  @return {Service} The service matching the alias
		*/
		protected function service($aliasOrClassName)
		{
			return Service::get($aliasOrClassName);
		}
		
		/**
		*  Get the Authorization class to handle things like login, password change
		*  @method user
		*  @protected
		*  @return {Authorization} Authorization instance
		*/
		protected function user()
		{
			return Site::instance()->getUser();
		}
		
		/**
		*  Get the instance of the site cache
		*  @method cache
		*  @protected
		*  @return {ServerCache} The ServerCache instance
		*/
		protected function cache()
		{
			return Site::instance()->getCache();
		}
		
		/**
		*  Convenience getter for the site
		*  @method site
		*  @protected
		*  @return {Canteen} Singleton instance of Canteen
		*/
		protected function site()
		{
			return Site::instance();
		}
	}
}