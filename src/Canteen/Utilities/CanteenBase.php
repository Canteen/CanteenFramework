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
		*  Get the site setting or settings
		*  @method settings
		*  @protected
		*  @param {String} [name=null] The name of the config property
		*  @return {String|Dictionary} Either value of the data or all items if param name is null
		*/
		protected function settings($name=null)
		{
			$settings = Site::instance()->settings;
			
			// If there's a name
			if ($name !== null) $settings = ifsetor($settings[$name], null);

			if ($settings === null)
			{
				throw new CanteenError(CanteenError::INVALID_DATA, $name);
			}
			return $settings;
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
		*  Convenience method for parsing content
		*  @method parse
		*  @protected
		*  @param {String} content The string to parse
		*  @param {Dictionary} substitutions The dictionary of tags to replace
		*  @return {String} The parsed string
		*/
		protected function parse(&$content, $substitutions)
		{
			$content = $this->parser->parse($content, $substitutions);
			return $content;
		}
		
		/**
		*  Convenience method for parsing content
		*  @method template
		*  @protected
		*  @param {String} name The name of the template to parse
		*  @param {Dictionary} [substitutions=array()] The dictionary of tags to replace
		*  @return {String} The parsed string
		*/
		protected function template($name, $substitutions=array())
		{
			return $this->parser->template($name, $substitutions);
		}
		
		/**
		*  Convenience method for parsing content
		*  @method parse
		*  @protected
		*  @param {String} content The string to parse
		*  @return {String} The parsed string
		*/
		protected function removeEmpties(&$content)
		{
			$content = $this->parser->removeEmpties($content);
			return $content;
		}
		
		/**
		*   The getter
		*/ 
		public function __get($name)
		{			
			switch($name)
			{
				/**
				*  Convenience getter for the site
				*  @property {Site} site
				*  @readOnly
				*/
				case 'site' : return Site::instance();

				/**
				*  Get the instance of the site cache
				*  @property {ServerCache} cache
				*  @readOnly
				*/
				case 'cache' :
				
				/**
				*  Get the Authorization class to handle things like login, password change
				*  @property {Authorization} user
				*  @readOnly
				*/
				case 'user':
				
				/**
				*  The instance of the profiler for debugging performance
				*  @property {Profiler} profiler
				*  @readOnly
				*/
				case 'profiler':
								
				/**
				*  The parser is responsible for rendering templates
				*  @property {Parser} parser
				*  @readOnly
				*/
				case 'parser': return Site::instance()->$name;
			}
			return null;
		}
	}
}