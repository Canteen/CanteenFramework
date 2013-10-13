<?php

/**
*  @module Canteen\Parser
*/
namespace Canteen\Parser
{
	use Canteen\Profiler\Profiler;
	use Canteen\Errors\CanteenError;
	use Canteen\Utilities\Autoloader;
	use Canteen\Utilities\StringUtils;
	
	/**
	*  Simple string parser to use for doing html subs. Located in the namespace __Canteen\Utilities__.
	*  
	*  @class Parser
	*/
	class Parser
	{
		/**
		*  Prepare the site content to be displayed
		*  This does all of the data substitutions and url fixes
		*  @method parse
		*  @static
		*  @param {String} content The content data
		*  @param {Dictionary} substitutions The substitutions key => value replaces {{key}} in template
		*  @return {String} The parsed template
		*/
		public static function parse(&$content, $substitutions)
		{
			StringUtils::checkBacktrackLimit($content);
			
			// Don't proceed if the string is empty
			if (empty($content)) return $content;
			
			// If we contain subs, lets do it
			if (preg_match('/\{\{[^\}]*\}\}/', $content))
			{
				if (PROFILER) Profiler::start('Parse Templates');
				self::parseTemplates($content, $substitutions);
				StringUtils::checkBacktrackLimit($content);
				if (PROFILER) Profiler::end('Parse Templates');

				if (PROFILER) Profiler::start('Parse If Blocks');
				self::parseIfBlocks($content, $substitutions);
				StringUtils::checkBacktrackLimit($content);
				if (PROFILER) Profiler::end('Parse If Blocks');
				
				if (PROFILER) Profiler::start('Parse Loops');
				self::parseLoops($content, $substitutions);
				StringUtils::checkBacktrackLimit($content);
				if (PROFILER) Profiler::end('Parse Loops');

				if (PROFILER) Profiler::start('Parse Substitutions');
				// Do the template replacements
			   	foreach($substitutions as $id=>$val)
				{
					// Define the replace pattern
					if (self::contains($id, $content) && !is_array($val))
					{
						$content = preg_replace("/\{\{$id\}\}/", $val, $content);
					}
					StringUtils::checkBacktrackLimit($content);
				}
				if (PROFILER) Profiler::end('Parse Substitutions');
			}
			
			// Fix the links
			if (PROFILER) Profiler::start('Parse Fix Path');
			self::fixPath($content, ifconstor('BASE_PATH', ''));
			if (PROFILER) Profiler::end('Parse Fix Path');
			
			return $content;
		}
		
		/**
		*  Get the template by form name
		*  @method getTemplate
		*  @static
		*  @param {String} name The name of the template as defined in Autoloader
		*  @param {Dictionary} [substitutions=array()] The collection of data to substitute
		*/
		public static function getTemplate($name, $substitutions=array())
		{
			return self::parseFile(
				Autoloader::instance()->template($name),
				$substitutions
			);
		}
		
		/**
		*  Check to see if a string contains a sub tag
		*  @method contains
		*  @static
		*  @param {String} needle The tag name to look for with out the {{}}
		*  @param {String} haystack The string to search in
		*  @return {Boolean} If the tag is in the string
		*/
		public static function contains($needle, $haystack)
		{
			return preg_match("/\{\{$needle\}\}/", $haystack);
		}
		
		/**
		*  Remove the empty substitution tags
		*  @method removeEmpties
		*  @static 
		*  @param {String} content The content string
		*  @return {String} The content string
		*/
		public static function removeEmpties(&$content)
		{
			StringUtils::checkBacktrackLimit($content);
			$content = preg_replace('/\{\{[^\}]+\}\}/', '', $content);
			return $content;
		}
		
		/**
		*  Parse a url with substitutions
		*  @method parseFile
		*  @static
		*  @param {String} url The path to the template
		*  @param {Dictionary} substitutions The substitutions key => value replaces {{key}} in template
		*  @return {String} The parsed template
		*/
		public static function parseFile($url, $substitutions)
		{			
			$content = @file_get_contents($url);
			
			// If there's no file, don't do the rest of the regexps
			if ($content === false) 
			{
				throw new CanteenError(CanteenError::TEMPLATE_NOT_FOUND, $url);
			}
			
			// Do a regular parse with the string
			return self::parse($content, $substitutions);
		}
		
		/**
		*  Parse a content string if blocks based on configs
		*  @method parseIfBlocks
		*  @static
		*  @param {String} content The content string
		*  @param {Dictionary} substitutions The substitutions array
		*  @return {String} The updated content string
		*/
		private static function parseIfBlocks(&$content, $substitutions)
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
					$content = self::parseIfBlocks($content, $substitutions);
				}
			}
			return $content;
		}
		
		/**
		*  Parse a content string into loops
		*  @method parseLoops
		*  @static
		*  @param {String} content The content string
		*  @param {Dictionary} substitutions The substitutions array
		*  @return {String} The updated content string
		*/
		private static function parseLoops(&$content, $substitutions)
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
								$result .= self::parse($template, $sub);
							}
							else
							{
								error("Parsing for-loop substitution needs to be an array");
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
		*  @static
		*  @param {String} content The content string
		*  @param {Dictionary} substitutions The substitutions array
		*  @return {String} The updated content string
		*/
		private static function parseTemplates(&$content, &$substitutions)
		{
			// Check for template matches
			preg_match_all("/\{\{(template\:([a-zA-Z]+))\}\}/", $content, $matches);
			
			if (count($matches[1]))
			{
				foreach($matches[1] as $i=>$template)
				{
					$substitutions[$template] = self::getTemplate(
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
		*  @static
		*  @param {String} content The content string
		*  @param {Dictionary} basePath The string to prepend all src and href with
		*  @return {String} The content with paths fixed
		*/
		public static function fixPath(&$content, $basePath)
		{
			// Replace the path to the stuff
		    $content = preg_replace(
				'#(href|src)=["\']([^/][^:"\']*)["\']#', 
				'$1="'.$basePath.'$2"', 
				$content
			);
			return $content;
		}		
	}
}