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
	use Canteen\Services\Service;
	use Canteen\Events\CanteenEvent;
	use \Exception;
	
	/**
	*  Update the database to the latest version.  Located in the namespace __Canteen\Forms__.
	*  @class InstallerForm
	*  @extends Form
	*/
	class InstallerForm extends Form
	{		
		/**
		*  Process the form and handle the $_POST data.
		*/
		public function __construct()
		{
			$email = $this->verify(ifsetor($_POST['email']), Validate::EMAIL);
			$username = $this->verify(ifsetor($_POST['username']), Validate::FILE_NAME);
			$password = ifsetor($_POST['password']);
			$repeatpass = ifsetor($_POST['repeatpass']);
			$firstName = $this->verify(ifsetor($_POST['firstName']), Validate::NAMES);
			$lastName = $this->verify(ifsetor($_POST['lastName']), Validate::NAMES);
			$siteTitle = $this->verify(ifsetor($_POST['siteTitle'], ''), Validate::FULL_TEXT);
			$contentPath = $this->verify(ifsetor($_POST['contentPath'], 'assets/html/content/'), Validate::URI);
			$templatePath = $this->verify(ifsetor($_POST['templatePath'], 'assets/html/index.html'), Validate::URI);
			
			if (!$email)
				$this->error('Email is required');
				
			if (!$username)
				$this->error('Username is required');
				
			if (!$password)
				$this->error('Password is required');
			else if ($password != $repeatpass)
				$this->error('Password and Repeat Password don\'t match');
			
			if (!$firstName)
				$this->error('First Name is required');
				
			if (!$lastName)
				$this->error('Last Name is required');
				
			if (!$siteTitle)
				$this->error('Site Title is required');
			
			if ($this->ifError) return;
			
			$config = Service::register('config', new ConfigService);
			$page = Service::register('page', new PageService);
			$user = Service::register('user', new UserService);
			
			$config->setup($siteTitle, $contentPath, $templatePath);
			$user->setup($username, $email, $password, $firstName, $lastName);
			$page->setup();

			// Trigger the site event for being installed
			$this->site->trigger(CanteenEvent::INSTALLED);

			// We're already installed, no need to keep checking
			// for the rest of this session
			$_COOKIE['installed'] = true;

			// redirect to the home page
			$this->redirect();
		}
	}
}	