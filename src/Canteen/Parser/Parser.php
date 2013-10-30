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
			
			// Get the first location of the opening tag
			$i = strpos($content, self::LEX_OPEN); 
			
			// Ignore if there are no subs
			if ($i === false) return $content;
			
			if ($profiler) $profiler->start('Parse Main');
			
			// The total length of the string
			$len = strlen($content);
			
			// length of the lexers
			$closeLen = strlen(self::LEX_CLOSE);
			$openLen = strlen(self::LEX_OPEN);
			$ifLen = strlen(self::LEX_IF);
			$notLen = strlen(self::LEX_NOT);
			$loopLen = strlen(self::LEX_LOOP);
			$tempLen = strlen(self::LEX_TEMPLATE);
			
			// Limit
			$j = 0;
			
			// While looping through the string content
			while($i < $len)
			{
				// open a tag!
				$tag = substr(
					$content, 
					$i + $openLen, 
					strpos(substr($content, $i + $closeLen), self::LEX_CLOSE)
				);
					
				// check for if tags
				if (strpos($tag, self::LEX_IF) !== false)
				{
					// Get the tag ID, the tag without the conditional lexer
					$id = substr($tag, $ifLen);
											
					// The string of the opening and closing tags
					$opening = self::LEX_OPEN . self::LEX_IF . $id . self::LEX_CLOSE;
					$closing = self::LEX_OPEN . self::LEX_IF_END . $id . self::LEX_CLOSE;
					
					// The if blocks can use the negative logic operator
					// to show the content, e.g. {{if:!debug}} == not debug mode
					$isNot = strpos($id, self::LEX_NOT) === 0;
					$name = ($isNot) ? substr($id, $notLen) : $id;
					
					// Remove the if tags, keep the content
					if ($isNot != StringUtils::asBoolean(ifsetor($substitutions[$name])))
					{
						$content = StringUtils::replaceOnce($opening, '', $content);
						$content = StringUtils::replaceOnce($closing, '', $content);
					}
					else
					{
						// Remove all content from the start 
						// the end of the last 
						$content = substr_replace(
							$content, '', $i, 
							// The position at the end of the last tag
							strpos(substr($content, $i), $closing) + strlen($closing)
						);
					}
				}
				// Check for loops
				else if (strpos($tag, self::LEX_LOOP) !== false)
				{
					// Get the name name without the loop label
					$id = substr($tag, $loopLen);
					
					if (isset($substitutions[$id]) && is_array($substitutions[$id]))
					{
						// The string of the opening and closing tags
						$opening = self::LEX_OPEN . self::LEX_LOOP . $id . self::LEX_CLOSE;
						$closing = self::LEX_OPEN . self::LEX_LOOP_END . $id . self::LEX_CLOSE;

						// The closing position
						$closingPos = strpos($content, $closing);
						
						// The buffer of looped items
						$buffer = '';

						$template = substr(
							$content, 
							$i + strlen($opening), // starting position 
							$closingPos - $i - strlen($opening) // length
						);
						
						// Loop through all the items
						foreach($substitutions[$id] as $sub)
						{				
							// If the item is an object
							if (is_object($sub)) 
								$sub = get_object_vars($sub);

							// The item should be an array
							if (is_array($sub))
							{
								$templateClone = $template;
								$buffer .= $this->parse($templateClone, $sub);
							}
							else
							{
								error('Parsing for-loop substitution needs to be an array');
							}
						}
						
						// Remove all content from the start 
						// the end of the last 
						$content = substr_replace(
							$content, $buffer, $i, 
							// The position at the end of the last tag
							strpos(substr($content, $i), $closing) + strlen($closing)
						);
						
						// Clear variables
						unset($buffer, $template, $templateClone);
					}			
				}
				// Check for templates
				else if (strpos($tag, self::LEX_TEMPLATE) !== false)
				{
					// Get the name name without the loop label
					$id = substr($tag, $tempLen);
					
					$content = StringUtils::replaceOnce(
						self::LEX_OPEN . self::LEX_TEMPLATE . $id . self::LEX_CLOSE,
						$this->getContents($id, $substitutions),
						$content
					);
				}
				else
				{
					// Tag wasn't avaliable or is invalid, lets move on
					$i += $openLen;
				}
				
				// Update the string length
				$len = strlen($content);
				$nextTag = strpos(substr($content, $i), self::LEX_OPEN);
				
				// If we don't have any tags left, bail out!
				if (empty($len) || $nextTag === false) break;
				
				// Get the position of the next open tag
				$i += $nextTag;
				
				if (++$j > $this->limit)
				{
					error('Parser maximum number of loops reached, please your parser syntax for errors');
					break;
				}
			}
			if ($profiler) $profiler->end('Parse Main');
			
			if ($profiler) $profiler->start('Parse Tags');
			foreach($substitutions as $tag=>$value)
			{
				// Ignore arrays, objects and tags that aren't set
				if (is_array($value) || is_object($value) || !$this->contains($tag, $content)) 
					continue;
					
				$this->single($content, $tag, $value);
			}
			if ($profiler) $profiler->end('Parse Tags');
				
			return $content;
		}
		
		/**
		*  Parse a single tag
		*  @method single
		*  @param {String} content The content data
		*  @param {String} tag The name of the tag
		*  @param {String} value The value to substitute
		*  @return {String} The updated string contents
		*/
		public function single(&$content, $tag, $value)
		{
			$content = str_replace(
				self::LEX_OPEN.$tag.self::LEX_CLOSE, 
				(string)$value, 
				$content
			);
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