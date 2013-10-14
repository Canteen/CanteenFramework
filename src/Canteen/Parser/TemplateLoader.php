<?php

/**
*  @module Canteen\Parser
*/
namespace Canteen\Parser
{	
	use Canteen\Errors\CanteenError;
	use Canteen\Utilities\StringUtils;
	use Canteen\Utilities\JSONUtils;
	
	/**
	*  Responsible for the autoloading of the parser templates.
	*  Located in the namespace __Canteen\Parser__.
	*  @class TemplateLoader
	*/
	class TemplateLoader
	{		
		/** 
		*  The list of valid templates 
		*  @property {Array} _templates
		*  @private
		*/
		private $_templates;
	
		/**
		*  Create the loader
		*/
		public function __construct()
		{
			$this->_templates = array();
		}
		
		/**
		*  Add a single template
		*  @method addTemplate
		*  @param {String} The alias name of the template
		*  @param {String} The full path to the template file
		*/
		public function addTemplate($name, $path)
		{
			if (isset($this->_templates[$name]))
			{
				throw new CanteenError(CanteenError::AUTOLOAD_TEMPLATE, $name);
			}
			$this->_templates[$name] = $path;
		}
		
		/**
		*  Register a directory that matches the Canteen structure
		*  @method addManifest
		*  @param {String} manifestPath The path of the manifest JSON to autoload
		*/
		public function addManifest($manifestPath)
		{			
			// Load the manifest json
			$templates = JSONUtils::load($manifestPath, false);
			
			// Get the directory of the manifest file
			$dir = dirname($manifestPath).'/';	
			
			// Include any templates
			if (isset($templates))
			{
				foreach($templates as $t)
				{
					$this->addTemplate(basename($t, '.html'), $dir . $t);
				}
			}
		}
		
		/**
		*  Get a template by name
		*  @method getPath
		*  @param {String} name The template name
		*  @return {String} The path to the template
		*/
		public function getPath($template)
		{
			if (isset($this->_templates[$template]))
			{
				return $this->_templates[$template];
			}
			throw new CanteenError(CanteenError::TEMPLATE_UNKNOWN, $template);
		}
		
		/**
		*  Get a template content 
		*  @method getContents
		*  @param {String} The name of the template
		*  @return {String} The string contents of the template
		*/
		public function getContents($template)
		{
			$path = $this->getPath($template);
			
			$contents = @file_get_contents($path);
			
			// If there's no file, don't do the rest of the regexps
			if ($contents === false)
			{
				throw new CanteenError(CanteenError::TEMPLATE_NOT_FOUND, $path);
			}
			
			return $contents;
		}
	}
}