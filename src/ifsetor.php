<?php
	
	/**
	*  @module global
	*/
	
	/**
	*  Global function to evaluate if a variable is set, or else use  a default value.
	*   __This is a global function.__
	*  
	*	$someValue = ifsetor($_POST['someValue'], 10);
	*
	*  @class ifsetor
	*  @constructor
	*  @param {mixed} val The variable to check if isset
	*  @param {mixed] [default=null] The default value if the variable isn't set
	*  @return {mixed} The value of the variable or the default
	*/
	function ifsetor(&$val, $default = null)
	{
		return isset($val) ? $val : $default;
	}