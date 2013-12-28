<?php

/**
*  @module Canteen\Controllers
*/
namespace Canteen\Controllers
{
	use Canteen\Utilities\StringUtils;
	use Canteen\Utilities\PasswordUtils;
	
	/**
	*  The controller for the forgot password flow.
	*  Located in the namespace __Canteen\Controllers__.
	*  
	*  @class ForgotPasswordController
	*  @extends Controller 
	*/
	class ForgotPasswordController extends Controller
	{
		/**
		*  Process the controller and build the view
		*  @method process
		*/
		public function process()
		{
			// Forgot password is only for users not loggedin
			if (LOGGED_IN)
			{
				redirect('admin/password');
				return;
			}
			
			$data = array();
			
			if ($this->dynamicUri)
			{
				$data['handle'] = true;
				
				$uri = explode('/', $this->dynamicUri);
				if (count($uri) != 2)
				{
					redirect('forgot-password');
				}
				list($username, $forgotString) = $uri;
				
				$user = $this->service('user')->getUser($username);
				$result = $this->service('user')->verifyResetPassword($username, $forgotString);
				
				if (!$result || !$user)
				{
					$data['error'] = 'Invalid forgot password URL.';
				}
				else
				{
					$password = StringUtils::generateRandomString();
					$hash = PasswordUtils::hash($password);
					
					$result = $this->service('user')->updateUser(
						$user->id, 
						array(
							'password' => $hash,
							'forgotString' => ''
						)
					);
					
					if (!$result)
					{
						$data['error'] = "There was a problem resetting your password. Try again.";
					}
					else
					{
						$data['success'] = 'Please login with this temporary password';
						$data['password'] = $password;
					}
				}
			}
			$this->page->content = $this->template('ForgotPassword', $data);
		}
	}
}