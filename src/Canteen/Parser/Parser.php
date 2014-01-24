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
	use Canteen\Errors\FileError;
	
	/**
	*  Simple string parser to use for doing html subs. Located in the namespace __Canteen\Utilities__.
	*  
	*  @class Parser
	*  @extends CanteenBase
	*/
	class Parser extends CanteenBase
	{
		/**
		*   Lexer for the opening of a parse tag
		*   @property {String} LEX_OPEN
		*   @static
		*   @final
		*   @default '{{'
		*/
		const LEX_OPEN = '{{';
			
		/**
		*   Lexer for the closing of a parse tag
		*   @property {String} LEX_CLOSE
		*   @static
		*   @final
		*   @default '}}'
		*/
		const LEX_CLOSE = '}}';
		
		/**
		*   Lexer for definition of an if parse tag
		*   @property {String} LEX_IF
		*   @static
		*   @final
		*   @default 'if:'
		*/
		const LEX_IF = 'if:';
		
		/**
		*   Lexer for if logical operator
		*   @property {String} LEX_NOT
		*   @static
		*   @final
		*   @default '!'
		*/
		const LEX_NOT = '!';
		
		/**
		*   Lexer for if closing if tag
		*   @property {String} LEX_IF_END
		*   @static
		*   @final
		*   @default '/if:'
		*/
		const LEX_IF_END = '/if:';
		
		/**
		*   Lexer for if opening loop tag
		*   @property {String} LEX_LOOP
		*   @static
		*   @final
		*   @default 'for:'
		*/
		const LEX_LOOP = 'for:';

		/**
		*   The property seperator similar to object "->"
		*   @property {String} LEX_SEP
		*   @static
		*   @final
		*   @default '.'
		*/
		const LEX_SEP = '.';
		
		/**
		*   Lexer for if closing loop tag
		*   @property {String} LEX_LOOP_END
		*   @static
		*   @final
		*   @default '/for:'
		*/
		const LEX_LOOP_END = '/for:';
		
		/**
		*   Lexer for if defining a template
		*   @property {String} LEX_TEMPLATE
		*   @static
		*   @final
		*   @default 'template:'
		*/
		const LEX_TEMPLATE = 'template:';
		
		/** 
		*  The list of valid templates 
		*  @property {Array} _templates
		*  @private
		*/
		private $_templates;
		
		/** 
		*  The maximum number of loops to parse before bailing
		*  @property {int} limit
		*  @default 10000
		*/
		public $limit = 10000;
	
		/**
		*  Create the loader
		*/
		public function __construct()
		{
			$this->_templates = [];
		}
		
		/**
		*  Add a single template
		*  @method addTemplate
		*  @param {String} name The alias name of the template
		*  @param {String} path The full path to the template file
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
				throw new FileError(FileError::FILE_EXISTS, $path);
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
				$errors = [
					JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
					JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
					JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
					JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON'
				];
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
			$content = $this->internalParse($content, $substitutions);
			return $content;
		}
		
		/**
		*  Prepare the site content to be displayed
		*  This does all of the data substitutions and url fixes. The order of operations
		*  is to do the templates, loops, if blocks, then individual substitutions. 
		*  @method parse
		*  @private
		*  @param {String} content The content data
		*  @param {Dictionary} substitutions The substitutions key => value replaces {{key}} in template
		*  @return {String} The parsed template
		*/
		private function internalParse($content, $substitutions)
		{
			StringUtils::checkBacktrackLimit($content);
			
			// Don't proceed if the string is empty
			if (empty($content)) return $content;
			
			// If the constant is defined
			$profiler = $this->profiler;
			
			// Search pattern for all
			$pattern = '/'.self::LEX_OPEN.'('
					.self::LEX_LOOP.'|'
					.self::LEX_TEMPLATE.'|'
					.self::LEX_IF.'|'
					.self::LEX_IF.self::LEX_NOT.
				')'
				.'([a-zA-Z0-9\''.self::LEX_SEP.']+)'.self::LEX_CLOSE.'/';
				
			preg_match_all($pattern, $content, $matches);
			
			if (count($matches))
			{			
				if ($profiler) $profiler->start('Parse Main');
				
				// length of opening and closing
				$closeLen = strlen(self::LEX_CLOSE);
				$openLen = strlen(self::LEX_OPEN);

				// Loop through all of the matches in order
				foreach($matches[0] as $i=>$tag)
				{
					$modifier = $matches[1][$i];
					$id = $matches[2][$i];
					$o1 = strpos($content, $tag);
					$o2 = $o1 + strlen($tag);

					if ($o1 === false) continue;

					// Get the tag prefix
					switch($modifier)
					{
						case self::LEX_IF :
						case self::LEX_IF.self::LEX_NOT :
						{
							$isNot = $modifier == self::LEX_IF.self::LEX_NOT;
							$endTag = self::LEX_OPEN . self::LEX_IF_END 
								. ($isNot ? self::LEX_NOT : '')
								. $id . self::LEX_CLOSE;
							
							// Remove the tags if content is true
							$value = $this->lookupValue($substitutions, $id);
							
							// The position order $o1{{if:}}$o2...$c2{{/if:}}$c1
							$c2 = strpos($content, $endTag);
							$c1 = $c2  + strlen($endTag);
							
							// There's no ending tag, we shouldn't continue
							// maybe we should throw an exception here
							if ($c2 === false) continue;
							
							// Default is to replace with nothing
							$buffer = '';
							
							// If statement logic
							if ($isNot != StringUtils::asBoolean($value))
							{
								// Get the contents of if and parse it
								$buffer = $this->internalParse(
									substr($content, $o2, $c2 - $o2),
									$substitutions
								);
							}
							// Remove the if statement and it's contents							
							$content = substr_replace($content, $buffer, $o1, $c1 - $o1);
							
							break;
						}
						case self::LEX_LOOP :
						{
							if($profiler) $profiler->start('Parse Loop');
												
							$endTag = self::LEX_OPEN . self::LEX_LOOP_END . $id . self::LEX_CLOSE;
							
							// The position order $o1{{for:}}$o2...$c2{{/for:}}$c1
							$c2 = strpos($content, $endTag);
							$c1 = $c2  + strlen($endTag);
							
							// There's no ending tag, we shouldn't continue
							// maybe we should throw an exception here
							if ($c2 === false) continue;
							
							$value = $this->lookupValue($substitutions, $id);

							// Remove the loop contents if there's no data
							if ($value === null || !is_array($value))
							{
								$content = substr_replace($content, '', $o1, $c1 - $o1);
								if($profiler) $profiler->end('Parse Loop');
								continue;
							}

							$buffer = '';
							$template = substr($content, $o2, $c2 - $o2);

							foreach($value as $sub)
							{				
								// If the item is an object
								if (is_object($sub))
									$sub = get_object_vars($sub);
							
								// The item should be an array
								if (!is_array($sub))
								{
									error('Parsing for-loop substitution needs to be an array');
									continue;
								}
								$buffer .= $this->internalParse($template, $sub);
							}

							// Replace the template with the buffer
							$content = substr_replace($content, $buffer, $o1, $c1 - $o1);
							if($profiler) $profiler->end('Parse Loop');
							break;
						}
						case self::LEX_TEMPLATE :
						{
							$template = $this->template($id, $substitutions);
							$content = preg_replace('/'.$tag.'/', $template, $content);
							break;
						}
					}
				}
				if ($profiler) $profiler->end('Parse Main');
			}
			
			$pattern = '/'.self::LEX_OPEN.'([a-zA-Z0-9\''.self::LEX_SEP.']+)'.self::LEX_CLOSE.'/';
			preg_match_all($pattern, $content, $matches);
			
			if (count($matches))
			{
				if ($profiler) $profiler->start('Parse Singles');
				foreach($matches[0] as $i=>$tag)
				{
					$id = $matches[1][$i];
					$value = $this->lookupValue($substitutions, $id);
				
					// only do replacements if the id exists in the substitutions
					// there might be another pass that actually does the replacement
					// for instance the Canteen parse then the Controller parse
					if ($value === null) continue;

					if (is_array($value))
						throw new CanteenError(CanteenError::PARSE_ARRAY, [$id, implode(', ', $value)]);
					
					$content = preg_replace('/'.$tag.'/', (string)$value, $content);
				}
				if ($profiler) $profiler->end('Parse Singles');
			}
			return $content;
		}

		/**
		*  Get the nested value for a dot-syntax array/object lookup
		*  For instance, `getNextVar($substitutions, 'event.name')`
		*  @method lookupValue
		*  @private
		*  @param {Dictionary|Object} context The associative array or object
		*  @param {String} name The do matrix name
		*  @return {mixed} The value of the lookup
		*/
		private function lookupValue($context, $name)
		{
		    $pieces = explode(self::LEX_SEP, $name);
		    foreach($pieces as $piece)
		    {
		        if (is_array($context) && array_key_exists($piece, $context))
		        {
		           $context = $context[$piece];
		        }
		        else if (is_object($context) && property_exists($context, $piece))
		        {
		        	$context = $context->$piece;
		        }
		        else
		        {
		        	return null;
		        }
		    }
		    return $context;
		}
		
		/**
		*  Get the template by form name
		*  @method template
		*  @param {String} name The name of the template as defined in Autoloader
		*  @param {Dictionary} [substitutions=[]] The collection of data to substitute
		*/
		public function template($name, $substitutions=[])
		{
			return $this->internalParse($this->getContents($name), $substitutions);
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
			return strpos($haystack, self::LEX_OPEN.$needle.self::LEX_CLOSE) !== false;
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
			return $this->internalParse($content, $substitutions);
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