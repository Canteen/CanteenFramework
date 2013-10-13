<?php
	
	/**
	*  @module global
	*/
	
	/**
	*  Redirect to another page on the local site.  
	*  __This is a global function.__
	*
	*	redirect('about');
	*  
	*  @class redirect
	*  @constructor
	*  @param {String} [uri=''] The URI stub to redirect to
	*  @return {String} If the request is made asynchronously, returns the json redirect object as a string
	*/
	function redirect($uri='')
    {
		$uri = $uri . (QUERY_STRING ? '/' . QUERY_STRING : '');
		if (ifconstor('ASYNC_REQUEST', false))
		{
			echo json_encode(array('redirect' => $uri));
		}
		else
		{
			header('Location: '. HOST . BASE_PATH . $uri);
		}
		die();
    }