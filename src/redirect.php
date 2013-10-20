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
		$query = ifconstor('QUERY_STRING');
		$uri = $uri . ($query ? '/' . $query : '');
		if (ifconstor('ASYNC_REQUEST', false))
		{
			echo json_encode(array('redirect' => $uri));
		}
		else
		{
			$host = ifconstor('HOST', '//'.ifsetor($_SERVER['HTTP_HOST']));
			$basePath = ifconstor('BASE_PATH', dirname($_SERVER['PHP_SELF'].'/'));
			header('Location: '.  $host . $basePath . $uri);
		}
		die();
    }