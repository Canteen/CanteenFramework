<?php

	if(!function_exists('fnmatch')) 
	{
		function fnmatch($pattern, $string) 
		{
			return preg_match("#^".strtr(preg_quote($pattern, '#'), ['\*' => '.*', '\?' => '.'])."$#i", $string);
		}
	}