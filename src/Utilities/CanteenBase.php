<?php

/**
*  @module Canteen\Utilities
*/
namespace Canteen\Utilities
{
	use Canteen\Site;
	use Canteen\Errors\CanteenError;
	use Canteen\Services\Service;
	use Canteen\Events\EventDispatcher;
	
	/**
	*  Common functionality for Services, Controllers, Forms. Located in the namespace __Canteen\Utilities__.
	*  
	*  @class CanteenBase
	*/
	abstract class CanteenBase extends EventDispatcher
	{		
		/**
		*  Get a service by alias
		*  @method service
		*  @protected
		*  @param {String} alias The alias
		*  @return {Service} The service matching the alias
		*/
		protected function service($alias)
		{
			return Service::get($alias);
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
		*  @param {Dictionary} [substitutions=[]] The dictionary of tags to replace
		*  @return {String} The parsed string
		*/
		protected function template($name, $substitutions=[])
		{
			return $this->parser->template($name, $substitutions);
		}

		/**
		*  Do a page redirect
		*  @method redirect
		*  @protected
		*  @param {String} [uri=''] Redirect the page
		*/
		protected function redirect($uri='')
		{
			return $this->site->redirect($uri);
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
				*  Get the instance of the settings manager
				*  @property {SettingsManager} settings
				*  @readOnly
				*/
				case 'settings' :
				
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