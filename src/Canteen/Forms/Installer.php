<?php

/**
*  @module Canteen\Forms
*/
namespace Canteen\Forms
{
	use Canteen\Utilities\Validate;
	use Canteen\Services\ConfigService;
	use Canteen\Services\PageService;
	use Canteen\Services\UserService;
	use \Exception;
	
	/**
	*  Update the database to the latest version.  Located in the namespace __Canteen\Forms__.
	*  @class Installer
	*  @extends FormBase
	*/
	class Installer extends FormBase
	{	
		/**
		*  Process the form and handle the $_POST data.
		*/
		public function __construct()
		{			
			$email = ifsetor($_POST['email']);
			$username = ifsetor($_POST['username']);
			$password = ifsetor($_POST['password']);
			$repeatpass = ifsetor($_POST['repeatpass']);
			$firstName = ifsetor($_POST['firstName']);
			$lastName = ifsetor($_POST['lastName']);
			$siteTitle = ifsetor($_POST['siteTitle'], '');
			$contentPath = ifsetor($_POST['contentPath'], 'assets/html/content/');
			$templatePath = ifsetor($_POST['templatePath'], 'assets/html/index.html');
			
			if (!$email)
			{
				$this->error('Email is required');
			}
			if (!$username)
			{
				$this->error('Username is required');
			}
			if (!$password)
			{
				$this->error('Password is required');
			}
			else if ($password != $repeatpass)
			{
				$this->error('Password and Repeat Password don\'t match');
			}
			if (!$firstName)
			{
				$this->error('First Name is required');
			}
			if (!$lastName)
			{
				$this->error('Last Name is required');
			}
			if (!$siteTitle)
			{
				$this->error('Site Title is required');
			}
			
			if (!$this->ifError)
			{
				new ConfigService;
				try
				{
					// catch the error getting contentPath
					new PageService;
				}
				catch(Exception $e){}
				new UserService;

				$this->service('config')->install($siteTitle, $contentPath, $templatePath);
				$this->service('users')->install($username, $email, $password, $firstName, $lastName);
				$this->service('pages')->install();

				// We're already installed, no need to keep checking
				// for the rest of this session
				$_SESSION['installed'] = true;

				// redirect to the home page
				redirect();
			}	
		}
	}
}	