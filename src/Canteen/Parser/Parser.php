<?php

/**
*  @module Canteen\Parser
*/
namespace Canteen\Parser
{
	use Canteen\Site;
	use Canteen\Profiler\Profiler;
	use Canteen\Errors\CanteenError;
	use Canteen\Utilities\StringUtils;
	use Canteen\Utilities\CanteenBase;
	
	/**
	*  Simple string parser to use for doing html subs. Located in the namespace __Canteen\Utilities__.
	*  
	*  @class Parser
	*  @extends CanteenBase
	*/
	class Parser extends CanteenBase
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
			$templates = $this->load($manifestPath, false);
			
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
		*  Load a JSON file from a path, does the error checking
		*  @method load
		*  @private
		*  @param {String} path The path to the .json file
		*  @param {Boolean} [asAssociate=true] Return as associative array
		*  @return {Array} The native object or array
		*/
		private function load($path, $asAssociative=true)
		{
			if (!fnmatch('*.json', $path) || !file_exists($path))
			{
				throw new CanteenError(CanteenError::JSON_INVALID, $path);
			}
			
			$json = json_decode(file_get_contents($path), $asAssociative);
			
			if (empty($json))
			{
				throw new CanteenError(CanteenError::JSON_DECODE, $this->lastJsonError());
			}
			return $json;
		}
		
		/**
		*  Get the last JSON error message
		*  @method lastJsonError
		*  @private
		*  @return {String} The json error message
		*/
		private function lastJsonError()
		{
			// For PHP 5.5.0+
			if (function_exists('json_last_error_msg'))
			{
				return json_last_error_msg();
			}
			
			// If we can get the specific error, we should
			// Introduced in PHP 5.3.0
			if (function_exists('json_last_error'))
			{
				$errors = array(
					JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
					JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
					JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
					JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON'
				);
				// Introduced in PHP 5.3.3
				if (defined('JSON_ERROR_UTF8'))
				{
					$errors[JSON_ERROR_UTF8] = 'Malformed UTF-8 characters, possibly incorrectly encoded';
				}
				return ifsetor($errors[json_last_error()], '');
			}
			return '';
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
		
		/**
		*  Prepare the site content to be displayed
		*  This does all of the data substitutions and url fixes. The order of operations
		*  is to do the templates, loops, if blocks, then individual substitutions. 
		*  @method parse
		*  @param {String} content The content data
		*  @param {Dictionary} substitutions The substitutions key => value replaces {{key}} in template
		*  @return {String} The parsed template
		*/
		public function parse(&$content, $substitutions)
		{
			StringUtils::checkBacktrackLimit($content);
			
			// Don't proceed if the string is empty
			if (empty($content)) return $content;
			
			// If the constant is defined
			$profiler = $this->profiler;
			
			// If we contain subs, lets do it
			if (preg_match('/\{\{[^\}]*\}\}/', $content))
			{
				if ($profiler) $profiler->start('Parse Templates');
				$this->parseTemplates($content, $substitutions);
				StringUtils::checkBacktrackLimit($content);
				if ($profiler) $profiler->end('Parse Templates');
				
				if ($profiler) $profiler->start('Parse Loops');
				$this->parseLoops($content, $substitutions);
				StringUtils::checkBacktrackLimit($content);
				if ($profiler) $profiler->end('Parse Loops');
				
				if ($profiler) $profiler->start('Parse If Blocks');
				$this->parseIfBlocks($content, $substitutions);
				StringUtils::checkBacktrackLimit($content);
				if ($profiler) $profiler->end('Parse If Blocks');

				if ($profiler) $profiler->start('Parse Substitutions');
				// Do the template replacements
			   	foreach($substitutions as $id=>$val)
				{
					// Define the replace pattern
					if ($this->contains($id, $content) && !is_array($val))
					{
						$content = str_replace('{{'.$id.'}}', $val, $content);
					}
					StringUtils::checkBacktrackLimit($content);
				}
				if ($profiler) $profiler->end('Parse Substitutions');
			}	
			return $content;
		}
		
		/**
		*  Get the template by form name
		*  @method template
		*  @param {String} name The name of the template as defined in Autoloader
		*  @param {Dictionary} [substitutions=array()] The collection of data to substitute
		*/
		public function template($name, $substitutions=array())
		{
			$contents = $this->getContents($name);
			return $this->parse($contents, $substitutions);
		}
		
		/**
		*  Check to see if a string contains a sub tag
		*  @method contains
		*  @param {String} needle The tag name to look for with out the {{}}
		*  @param {String} haystack The string to search in
		*  @return {Boolean} If the tag is in the string
		*/
		public function contains($needle, $haystack)
		{
			return strpos($haystack, '{{'.$needle.'}}') !== false;
		}
		
		/**
		*  Remove the empty substitution tags
		*  @method removeEmpties 
		*  @param {String} content The content string
		*  @return {String} The content string
		*/
		public function removeEmpties(&$content)
		{
			StringUtils::checkBacktrackLimit($content);
			$content = preg_replace('/\{\{[^\}]+\}\}/', '', $content);
			return $content;
		}
		
		/**
		*  Parse a url with substitutions
		*  @method parseFile
		*  @param {String} url The path to the template
		*  @param {Dictionary} substitutions The substitutions key => value replaces {{key}} in template
		*  @return {String} The parsed template
		*/
		public function parseFile($url, $substitutions)
		{			
			$content = @file_get_contents($url);
			
			// If there's no file, don't do the rest of the regexps
			if ($content === false) 
			{
				throw new CanteenError(CanteenError::TEMPLATE_NOT_FOUND, $url);
			}
			
			// Do a regular parse with the string
			return $this->parse($content, $substitutions);
		}
		
		/**
		*  Parse a content string if blocks based on configs
		*  @method parseIfBlocks
		*  @param {String} content The content string
		*  @param {Dictionary} substitutions The substitutions array
		*  @return {String} The updated content string
		*/
		private function parseIfBlocks(&$content, $substitutions)
		{
			$ifPattern = '/\{\{(if\:(\!?[a-zA-Z]+))\}\}(.*?)\{\{fi\:\2\}\}/s';
			
			// Check for if statements
			preg_match_all($ifPattern, $content, $matches);
			
			if (count($matches[2]))
			{			
				foreach($matches[2] as $i=>$bool)
				{
					$name = $bool;
					$isNot = substr($bool, 0, 1) == '!';
					
					if ($isNot) $name = substr($bool, 1);	
					
					// Decide whether to remove the tags or the content
					$pattern = $isNot != StringUtils::asBoolean(ifsetor($substitutions[$name])) ? 
						'/\{\{(if|fi)\:'.$bool.'\}\}/':
						'/\{\{(if\:('.$bool.'))\}\}(.*?)\{\{fi\:\2\}\}/s';
					
					$content = preg_replace($pattern, '', $content);
					
					// Check for nested blocks
					$content = $this->parseIfBlocks($content, $substitutions);
				}
			}
			return $content;
		}
		
		/**
		*  Parse a content string into loops
		*  @method parseLoops
		*  @param {String} content The content string
		*  @param {Dictionary} substitutions The substitutions array
		*  @return {String} The updated content string
		*/
		private function parseLoops(&$content, $substitutions)
		{
			// Check for template matches
			preg_match_all('/\{\{(for\:([a-zA-Z]+))\}\}(.*?)\{\{endfor\:\2\}\}/s', $content, $matches);
			
			if (count($matches[1]))
			{
				foreach($matches[2] as $i=>$id)
				{
					if (isset($substitutions[$id]) && is_array($substitutions[$id]))
					{
						$result = '';
						foreach($substitutions[$id] as $sub)
						{
							if (is_object($sub)) $sub = get_object_vars($sub);
							
							if (is_array($sub))
							{
								$template = $matches[3][$i];
								$result .= $this->parse($template, $sub);
							}
							else
							{
								error('Parsing for-loop substitution needs to be an array');
							}
						}
						
						// Substitute all of the loops
						$content = preg_replace(
							'/\{\{(for\:('.$id.'))\}\}(.*?)\{\{endfor\:\2\}\}/s',
							$result, 
							$content
						);
					}
				}
			}
			return $content;
		}
		
		/**
		*  Search for and parse templates {{template:SomeTemplate}}
		*  @method parseTemplates
		*  @param {String} content The content string
		*  @param {Dictionary} substitutions The substitutions array
		*  @return {String} The updated content string
		*/
		private function parseTemplates(&$content, &$substitutions)
		{
			// Check for template matches
			preg_match_all("/\{\{(template\:([a-zA-Z]+))\}\}/", $content, $matches);
			
			if (count($matches[1]))
			{
				foreach($matches[1] as $i=>$template)
				{
					$substitutions[$template] = $this->template(
						$matches[2][$i], 
						$substitutions
					);
				}
			}
			return $content;
		}
		
		/**
		*  Replaces any path (href/src) with the base
		*  @method fixPath
		*  @param {String} content The content string
		*  @param {Dictionary} basePath The string to prepend all src and href with
		*  @return {String} The content with paths fixed
		*/
		public function fixPath(&$content, $basePath)
		{
			// Replace the path to the stuff
		    $content = preg_replace(
				'/(href|src)=["\']([^\/][^:"\']*)["\']/', 
				'$1="'.$basePath.'$2"', 
				$content
			);
			return $content;
		}		
	}
}