<?php

/**
*  @module Canteen\Forms
*/
namespace Canteen\Forms
{
	use Canteen\Utilities\Validate;
	
	/**
	*  Form to login the user.  Located in the namespace __Canteen\Forms__.
	*  @class LoginForm
	*  @extends Form
	*/
	class LoginForm extends Form
	{	
		/**
		*  Process the form and handle the $_POST data.
		*/
		public function __construct()
		{
			// Check the user login form
			$username = ifsetor($_POST['username']);
			$password = ifsetor($_POST['password']);
			$remember = isset($_POST['remember']);
			
			$this->verify($username, Validate::EMAIL);
			
			// Create a user to check login
			if (!$this->user->login($username, $password, $remember))
			{
				$this->error($this->user->error);
			}
			else
			{
				$this->redirect($this->settings->uriRequest);
			}
		}
	}
}	