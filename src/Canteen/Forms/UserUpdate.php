<?php

/**
*  @module Canteen\Forms
*/
namespace Canteen\Forms
{
	use Canteen\Utilities\Validate;
	use Canteen\Utilities\PasswordUtils;
	use Canteen\Authorization\Privilege;
	use Canteen\Events\ObjectFormEvent;
	
	/**
	*  Update or add a user.  Located in the namespace __Canteen\Forms__.
	*  @class UserUpdate
	*  @extends ObjectForm
	*/
	class UserUpdate extends ObjectForm
	{
		/**
		*  The minimum characters needed for a password
		*  @property {int} MIN_PASSWORD_LENGTH
		*  @default 6
		*  @static
		*  @final
		*/
		const MIN_PASSWORD_LENGTH = 6;

		/**
		*  If we should process the password
		*  @property {Boolean} doPassword
		*  @default false
		*  @private
		*/
		private $doPassword = false;

		/**
		*  Constructor
		*/
		public function __construct()
		{
			$this->on(ObjectFormEvent::VALIDATE, array($this, 'onValidate'))
				->on(ObjectFormEvent::BEFORE_REMOVE, array($this, 'onBeforeRemove'))
				->on(ObjectFormEvent::BEFORE_UPDATE, array($this, 'onBeforeUpdate'))
				->on(ObjectFormEvent::BEFORE_ADD, array($this, 'onBeforeAdd'));

			parent::__construct(
				$this->service('user')->item,
				$this->settings->uriRequest
			);
		}

		/**
		*  Event handler before a page is removed
		*  @method onBeforeRemove
		*  @param {ObjectFormEvent} event The before remove event
		*/
		public function onBeforeRemove(ObjectFormEvent $event)
		{
			if ($event->object->id == USER_ID)
			{
				$this->error('You cannot delete yourself');
			}
		}

		/**
		*  Handler to do some validation before an add or update
		*  @method onValidate
		*  @param {ObjectFormEvent} event The beforeAdd or beforeUpdate event
		*/
		public function onValidate(ObjectFormEvent $event)
		{
			$email = ifsetor($_POST['email']);
			$firstName = ifsetor($_POST['firstName']);
			$lastName = ifsetor($_POST['lastName']);
			$username = ifsetor($_POST['username']);	
			$privilege = intval(ifsetor($_POST['privilege']));

			$isActive = $_POST['isActive'] = (boolean)ifsetor($_POST['isActive'], 0);

			if (!$email) 
				$this->error('Email is a required field');
			else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) 
				$this->error('Email is not valid');

			if (!$firstName) $this->error('First name is a required field');
			if (!$lastName) $this->error('Last name is a required field');
			if (!$username) $this->error('Username is a required field');

			if ($privilege < Privilege::GUEST || $privilege > Privilege::ADMINISTRATOR)
				$this->error('Not a valid privilege');

			$password = ifsetor($_POST['password']);
			$repeatPassword = ifsetor($_POST['repeatPassword']);

			if ($password)
			{
				$this->doPassword = true;
				
				if ($password != $repeatPassword)
				{
					$this->error('Password and repeat password don\'t match');
				}
				if (strlen($password) < self::MIN_PASSWORD_LENGTH)
				{
					$this->error('Password much be six ('.self::MIN_PASSWORD_LENGTH.') or more characters long');
				}
			}
		}

		/**
		*  Handler to do some extra checking before we update
		*  @method onBeforeUpdate
		*  @param {ObjectFormEvent} event The beforeUpdate event
		*/
		public function onBeforeUpdate(ObjectFormEvent $event)
		{
			$isSelf = $event->object->id == USER_ID;

			$password = ifsetor($_POST['password']);

			if ($this->doPassword)
			{
				if (PasswordUtils::validate($password, $event->object->password))
				{
					$this->error('Already the user\'s password');
				}

				// The user is trying to update their own password
				if ($isSelf && $this->user->updatePassword($password))
				{
					$this->success('Updated your password');
				}
				else
				{
					$_POST['password'] = PasswordUtils::hash($password);
				}
			}

			// User is trying to deactive themselves, we shouldn't do this!
			if ($isSelf)
			{
				// Only other admins can deactivate other admins
				// can't deactivate yourseld
				if ($event->object->isActive != $isActive)
				{
					$this->error('You cannot deactivate yourself');
				}

				// User is trying to change their privilege
				// only another administrator can do this
				if ($event->object->privilege != $privilege)
				{
					$this->error('You cannot change your privilege');
				}
			}
		}

		/**
		*  Handler to do some extra checking before we add
		*  @method onBeforeAdd
		*  @param {ObjectFormEvent} event The beforeAdd event
		*/
		public function onBeforeAdd(ObjectFormEvent $event)
		{
			if (!$this->doPassword)
			{
				$this->error('Password is required to add a new user');
				return;
			}

			$email = $_POST['email'];
			$user = $this->item->service->getUserByLogin($email);

			if ($user)
			{
				$this->error("A user already exists with the email address '$email'");
				return;
			}

			$username = $_POST['username'];
			$user = $this->service('user')->getUserByLogin($username);

			if ($user)
			{
				$this->error("A user already exists with the username '$username'");
				return;
			}

			// Hash the password
			$_POST['password'] = PasswordUtils::hash($_POST['password']);
		}
	}
}