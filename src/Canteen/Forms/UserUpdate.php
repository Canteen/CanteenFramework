<?php

/**
*  @module Canteen\Forms
*/
namespace Canteen\Forms
{
	use Canteen\Utilities\Validate;
	use Canteen\Utilities\PasswordUtils;
	use Canteen\Authorization\Privilege;
	
	/**
	*  Update or add a user.  Located in the namespace __Canteen\Forms__.
	*  @class UserUpdate
	*  @extends FormBase
	*/
	class UserUpdate extends FormBase
	{
		/**
		*  Process the form and handle the $_POST data.
		*/
		public function __construct()
		{
			$userId = $this->verify(ifsetor($_POST['userId']));
			$user = $this->service('user')->getUser($userId);
			
			// See if we're going to delete the page
			// if the delete button was clicked
			if (isset($_POST['deleteButton']))
			{
				// Make sure there's a valid page
				if (!$user)
				{
					$this->error('No user to delete');
				}
				else if ($userId == USER_ID)
				{
					$this->error('You cannot delete yourself!');
				}
				else
				{
					// Remove the page
					if (!$this->service('user')->removeUser($userId))
					{
						$this->error('Unable to delete the user');
					}			
				}
				
				if (!$this->ifError)
				{
					// Goto the main pages admin
					redirect('admin/users');
				}
				return;
			}
			
			$privilege = $this->verify(ifsetor($_POST['privilege']));
			$firstName = $this->verify(ifsetor($_POST['firstName']), Validate::NAMES);
			$lastName = $this->verify(ifsetor($_POST['lastName']), Validate::NAMES);
			$username = $this->verify(ifsetor($_POST['username']), Validate::URI);			
			$email = $this->verify(ifsetor($_POST['email']), Validate::EMAIL);
			$isActive = isset($_POST['isActive']);
			$password = ifsetor($_POST['password']);
			$repeatPassword = ifsetor($_POST['repeatPassword']);
			
			$doPassword = false;
			
			if (!$privilege || $privilege < Privilege::GUEST || $privilege > Privilege::ADMINISTRATOR)
				$this->error('Not a valid privilege');
			
			if (!$firstName) $this->error('First name is a required field');
				
			if (!$lastName) $this->error('Last name is a required field');
				
			if (!$email) $this->error('Email is a required field');
			
			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $this->error('Email is not valid');
			
			if (!$username) $this->error('Username is a required field');
			
			if ($password)
			{
				$doPassword = true;
				
				if ($password != $repeatPassword)
				{
					$this->error("Password and repeat password don't match");
				}
				if (strlen($password) < 6)
				{
					$this->error("Password much be six (6) or more characters long");
				}
				if (!$this->verify($password, Validate::ALPHA_NUMERIC, true))
				{
					$this->error('Password can only contain alpha numeric characters');
				}
			}
			
			// Don't process if we have errors
			if ($this->ifError) return;
			
			// Update user
			if ($user)
			{
				$properties = array();
				
				foreach(array('firstName', 'lastName', 'privilege', 'email', 'username', 'isActive') as $p)
				{
					if ($$p != $user->$p) $properties[$p] = $$p;
				}
				
				$isSelf = $user->id == USER_ID;
				
				if ($doPassword)
				{
					if (PasswordUtils::validate($password, $user->password))
					{
						$this->error('Already the user\'s password');
					}
					else
					{
						// The user is trying to update their own password
						if ($isSelf && $this->user->updatePassword($password))
						{
							$this->success('Updated your password');
						}
						else
						{
							$properties['password'] = PasswordUtils::hash($password);
						}
					}
				}
				
				// User is trying to deactive themselves, we shouldn't do this!
				if ($isSelf)
				{
					// Only other admins can deactivate other admins
					// can't deactivate yourseld
					if (isset($properties['isActive']) && !$isActive)
					{
						$this->error('You cannot deactivate yourself');
						return;
					}
					// User is trying to change their privilege
					// only another administrator can do this
					if (isset($properties['privilege']))
					{
						$this->error('You cannot change your privilege');
						return;
					}
				}
				
				if (!count($properties))
				{
					$this->error('Nothing to update');
					return;
				}
				
				$result = $this->service('user')->updateUser($userId, $properties);
				
				if (!$result)
				{
					$this->error('Unable to update user');
				}
				else
				{
					$this->success('Updated user');
				}
			}
			// Add new user
			else
			{
				if (!$doPassword)
				{
					$this->error('Password is required to add a new user');
					return;
				}
				
				$result = $this->service('user')->addUser(
					$username, 
					$email, 
					$password, 
					$firstName, 
					$lastName, 
					$privilege
				);
				
				if (!$result)
				{
					$this->error('Unable to add the user');
				}
				else
				{
					$this->success('Added new user');
				}
			}
		}
	}
}