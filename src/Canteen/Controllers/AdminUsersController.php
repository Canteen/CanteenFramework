<?php

/**
*  @module Canteen\Controllers
*/
namespace Canteen\Controllers
{
	use Canteen\Authorization\Privilege;
	
	/** 
	*  Controller to manage the user management form.
	*  Located in the namespace __Canteen\Controllers__.
	*  
	*  @class AdminUsersController
	*  @extends AdminController
	*/
	class AdminUsersController extends AdminController
	{
		/**
		*  Process the controller and build the view
		*  @method process
		*/
		public function process()
		{			
			$user = null;
			$userId = (int)ifsetor($_POST['userId']);
			
			if (!empty($userId))
			{
				$user = $this->service('users')->getUserById($userId);
			}	
			
			$data = array(
				'formLabel' => $user ? 'Update an Existing User' : 'Add a New User',
				'isActive' => 'checked',
				'users' => '',
				'hasUser' => false
			);
			
			if ($user)
			{
				$data['privileges'] = $this->getPrivileges($user->privilege);
				$data['id'] = $user->id;
				$data['firstName'] = $user->firstName;
				$data['lastName'] = $user->lastName;
				$data['username'] = $user->username;
				$data['email'] = $user->email;
				$data['isActive'] = $user->isActive ? 'checked' : '';
				$data['hasUser'] = true;
			}
			else
			{
				$data['privileges'] = $this->getPrivileges();
				$data['users'] = $this->getUsers();
			}
			
			// Update the page with the template
			$this->addTemplate('AdminUsers', $data);
		}
		
		/**
		*  Get the form select of privileges
		*/
		private function getPrivileges($privilege = 0)
		{
			$options = '';
			$privileges = Privilege::getAll();
			foreach($privileges as $i=>$label)
			{
				if ($i < Privilege::GUEST) continue;
				$option = html('option value='.$i, $label);
				if ($i == $privilege)
				{
					$option->selected = 'selected';
				}
				$options .= (string)$option;
			}
			return $options;
		}
		
		/**
		*  Get the users as options
		*  @param Ignore the user with this id
		*/
		private function getUsers()
		{
			$users = $this->service('users')->getUsers();
			$options = '';
			foreach($users as $user)
			{
				$option = html('option value='.$user->id, $user->fullname);
				$options .= (string)$option;
			}
			return $options;
		}
	}
}