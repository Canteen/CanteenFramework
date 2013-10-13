<?php

	/**
	*  @module global
	*/
	
	/**
	*  Global function to evaluate if a constant is set, or else use default.
	*   __This is a global function.__
	*  
	*	$c = ifconstor('MY_CONST', 10);
	*
	*  @class ifconstor
	*  @constructor
	*  @param {String} constName The constant name to check
	*  @param {mixed} default The default value if the constant isn't defined
	*  @return {mixed} The value of the constant or the default value
	*/
	function ifconstor($constName, $default)
	{
	    return defined($constName) ? constant($constName) : $default;
	}