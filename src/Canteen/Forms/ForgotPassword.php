<?php

/**
*  @module Canteen\Forms
*/
namespace Canteen\Forms
{
	use Canteen\Parser\Parser;
	
	/**
	*  Handling of the initial password forgot process
	*  Process for resetting password is:
	*  <ol>
	*  <li>Request using username or email address a password reset</li>
	*  <li>Click on link in email</li>
	*  <li>Get temporary password</li>
	*  </ol>
	*  Located in the namespace __Canteen\Forms__.
	*  @class ForgotPassword
	*  @extends FormBase
	*/
	class ForgotPassword extends FormBase
	{
		/**
		*  The number of seconds between reset requests
		*  @property {int} timeBlock
		*  @static 
		*  @default 300
		*/
		public static $timeBlock = 300;
		
		/**
		*  Process the form and handle the $_POST data.
		*/
		public function __construct()
		{
			$usernameOrEmail = ifsetor($_POST['usernameOrEmail']);
			$user = $this->service('user')->getUser($usernameOrEmail);
			
			if (!$usernameOrEmail)
			{
				$this->error("Must have a username (can also use email)");
			}
			else if (!$user)
			{
				$this->error("No user found matching username or email");
			}
			// block requests made within 5 minutes
			else if (time() - ifsetor($_SESSION['forgotPassword'], 0) < self::$timeBlock) 
			{
				$this->error("Check your email for password resetting instructions. "
					."If you didn't receive an email please wait a few minutes before resubmitting.");
			}
			
			if (!$this->ifError)
			{
				// populate with new forgot string
				$forgotString = uniqid();
				
				$result = $this->service('user')->updateUser(
					$user->id, 'forgotString', $forgotString);
				
				if (!$result)
				{
					$this->error("There was a problem");
				}
				else
				{
					$url = BASE_URL.'forgot-password/'.$user->username.'/'.$forgotString;
					
					if ($this->settings->local)
					{
						$this->success($url);
					}
					else
					{
						// We should replace this with a more abstract method 
						// of mailing notifications
						$siteTitle = $this->settings->siteTitle;
						
						$to = $user->email;
						$subject = 'Password Reset - ' . $siteTitle;
						$message = $this->template('PasswordRecovery', [
							'url' => $url,
							'siteTitle' => $siteTitle
						]);
						$headers  = 'MIME-Version: 1.0' . "\r\n";
						$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
						$headers .= 'From: no-reply@' . DOMAIN . "\r\n";
						$headers .= 'Reply-To: no-reply@' . DOMAIN . "\r\n";
						$headers .= 'X-Mailer: PHP/' . phpversion();
						
						if (!mail($to, $subject, $message, $headers))
						{
							$this->error("There was a problem reseting your password. Try again.");
						}
						else
						{
							// Save the current time we're submitting
							// so someone can't spam email
							$_SESSION['forgotPassword'] = time();
							
							// Let the user know this was successful
							$this->success("Check your email for instructions on resetting your password");
						}
					}	
				}	
			}
		}
	}
}