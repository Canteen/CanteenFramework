<?php

/**
*  @module Canteen\Forms
*/
namespace Canteen\Forms
{
	use Canteen\Utilities\PasswordUtils;
	use Canteen\Utilities\Validate;
	
	/**
	*  Form for the user to handle their password update.  Located in the namespace __Canteen\Forms__.
	*  @class PasswordForm
	*  @extends Form
	*/
	class PasswordForm extends Form
	{
		/**
		*  Process the form and handle the $_POST data.
		*/
		public function __construct()
		{
			$this->privilege();
			
			$oldPassword = ifsetor($_POST['oldPassword']);
			$newPassword = ifsetor($_POST['newPassword']);
			$repeatPassword = ifsetor($_POST['repeatPassword']);
			
			if (!PasswordUtils::validate($oldPassword, $this->settings->userHash))
			{
				$this->error('This is not your current password');
			}
			if ($oldPassword == $newPassword)
			{
				$this->error('This is your current password');
			}
			if ($newPassword != $repeatPassword)
			{
				$this->error('New password and repeat password don\'t match');
			}
			if (strlen($newPassword) < 6)
			{
				$this->error('Password much be six (6) or more characters long');
			}
			
			if (!$this->ifError)
			{
				if (!$this->user->updatePassword($newPassword))
				{
					$this->error('There was a problem updating your password. Try again.');
				}
				else
				{
					$this->success('Password updated!');
				}
			}
		}
	}
}