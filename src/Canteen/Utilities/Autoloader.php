<?php

/**
*  @module Canteen\Utilities
*/
namespace Canteen\Utilities
{	
	use Canteen\Errors\CanteenError;
	use Canteen\Utilities\StringUtils;
	
	/**
	*  Responsible for the autoloading of PHP classes as well as HTML templates and functions.
	*  Located in the namespace __Canteen\Utilities__.
	*  @class Autoloader
	*  @module Canteen\Utilities
	*  @constructor
	*  @param {String} namespace The name of the namespace
	*  @param {String} path The path to the namespace
	*/
	class Autoloader
	{
		/** 
		*  The singleton instance 
		*  @property {Autoloader} _instance
		*  @private 
		*  @static
		*/
		private static $_instance;
		
		/** 
		*  The list of valid templates 
		*  @property {Array} _templates
		*  @private
		*/
		private $_templates;
		
		/** 
		*  The collection of namespaces
		*  @property {Array} _namespaces
		*  @private
		*/
		private $_namespaces;
		
		/**
		*  Initialize the and get the singleton instance of this class
		*  @method init
		*  @static
		*  @param {String} namespace The Canteen namespace
		*  @param {String} path The path to the Canteen namespace
		*/
		public static function init($namespace, $path)
		{
			if (!isset(self::$_instance))
			{
				self::$_instance = new self($namespace, $path);
			}
		}
	
		/**
		*  Get the singleton instance
		*  @method instance
		*  @method instance
		*  @return {Autoloader} The singleton instance of the AutoLoader
		*/
		public static function instance()
		{
			return self::$_instance;
		}
		
		/**
		*  Add a new namespace
		*  @method register
		*  @param {String} namespace The name of the namespace
		*  @param {String} path The path to the namespace
		*/
		public function register($namespace, $path)
		{
			$this->_namespaces[$namespace] = StringUtils::requireSeparator($path);
		}
		
		/**
		*  Register a directory that matches the Canteen structure
		*  @method manifest
		*  @param {String} manifestPath The path of the manifest JSON to autoload
		*/
		public function manifest($manifestPath)
		{			
			// Load the manifest json
			$manifest = JSONUtils::load($manifestPath, false);
			
			// Get the directory of the manifest file
			$dir = dirname($manifestPath).'/';		
			
			// Include any templates
			if (isset($manifest->templates))
			{
				foreach($manifest->templates as $template)
				{
					$baseName = basename($template, '.html');
					if (isset($this->_templates[$baseName]))
					{
						throw new CanteenError(CanteenError::AUTOLOAD_TEMPLATE, $baseClass);
					}
					$this->_templates[$baseName] = $dir . $template;
				}
			}
			
			// Include any functions
			if (isset($manifest->includes))
			{
				foreach($manifest->includes as $f)
				{
					require_once $dir . $f;	
				}
			}
			
			// Include additional namespaces for autoloading classes
			if (isset($manifest->namespaces))
			{
				foreach($manifest->namespaces as $name=>$path)
				{
					$this->register($name, $dir . $path);
				}
			}
			
			// Include a manifest file
			if (isset($manifest->manifests))
			{
				foreach($manifest->manifests as $manifest)
				{
					$this->manifest($dir . $manifest);
				}
			}
		}
	
		/**
		*  Setup the register class
		*/
		private function __construct($namespace, $path)
		{
			$this->_templates = array();
			$this->_namespaces[$namespace] = $path;
			
			spl_autoload_register(array($this, 'autoload'));
		}
		
		/**
		*  Load the class name needed
		*  @method autoload
		*  @private
		*  @param {String} name The class requested by spl_autoload_register
		*/
		private function autoload($name)
		{
			foreach($this->_namespaces as $ns=>$path)
			{
				// Only look at names in the same namespace
				if (preg_match('/^'.$ns.'\\\/', $name))
				{					
					// Remove the HTML5 namespace
					$name = preg_replace('/^'.$ns.'\\\/', '', $name);

					// Convert the rest to directories
					$name = str_replace("\\", '/', $name);
					
					// Include the class relative to here
					include $path.$name.'.php';
				}
			}
		}
		
		/**
		*  Get a template by name
		*  @method template
		*  @param {String} name The template name
		*  @return {String} The path to the template
		*/
		public function template($name)
		{
			if (isset($this->_templates[$name]))
			{
				return $this->_templates[$name];
			}
		}
	}
}