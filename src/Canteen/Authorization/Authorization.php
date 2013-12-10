<?php

/**
*  @module Canteen\Authorization
*/
namespace Canteen\Authorization
{
	use Canteen\Utilities\PasswordUtils;
	use Canteen\Utilities\CanteenBase;
	use Canteen\Services\Objects\User;
	
	/**
	*  Responsible for handing the authorization of a user
	*  either through login, cookie or session. Located in the namespace __Canteen\Authorization__.
	*  
	*  @class Authorization
	*  @extends CanteenBase
	*/
	class Authorization extends CanteenBase
	{		
		/** 
		*  How long, in seconds, to save the login cookie 
		*  @property {int} loginExpires
		*  @default 2592000
		*/
		public $loginExpires = 2592000;

		/** 
		*  The name of the site cookie 
		*  @property {String} cookieName
		*  @default termiteLogin
		*/
		public $cookieName = 'termiteLogin';
	
		/** 
		*  The number of attempts that a user can make before being locked out 
		*  @property {int} loginAttempts
		*  @default 5
		*/
		public $loginAttempts = 5;

		/** 
		*  The number of minutes a user is locked from their account 
		*  @property {int} frozenMinutes
		*  @default 15
		*/
		public $frozenMinutes = 15;
		
		/** 
		*  Store an error if there was an issue logging in 
		*  @property {String} error
		*/
		public $error;
	
		/** 
		*  Form error when username and password are required
		*  @property {String} ERR_EMPTY
		*  @static
		*  @final
		*/
		const ERR_EMPTY = 'Username and password are required.';
		
		/** 
		*  Form error when the supplied login is incorrect
		*  @property {String} ERR_WRONG
		*  @static
		*  @final
		*/
		const ERR_WRONG = 'The supplied login is incorrect.';
		
		/** 
		*  Form error when the password you entered was incorrect
		*  @property {String} ERR_PASS
		*  @static
		*  @final
		*/
		const ERR_PASS = 'The password you entered was incorrect. Attempt ';
		
		/** 
		*  Form error when a user's account has been frozen temporarily because
		*  the maximum number of failed login attempts
		*  @property {String} ERR_FROZEN
		*  @static
		*  @final
		*/
		const ERR_FROZEN = 'Your account has been frozen temporarily because you 
						   reached the maximum number of failed login attempts.';
						
		/** 
		*  Form error when a user's account has been deactivated
		*  @property {String} ERR_DEACTIVATED
		*  @static
		*  @final
		*/				
		const ERR_DEACTIVATED = 'Your account has been deactivated.';
	
		/** 
		*  The user data 
		*  @property {User} _user
		*  @protected
		*/
		protected $_user;
		
		/** 
		*  The domain of this user 
		*  @property {String} _domain
		*  @protected
		*/
		protected $_domain;
	
		/** 
		*  If there is a user logged in 
		*  @property {Boolean} _loggedin
		*  @protected
		*/
		protected $_loggedin = false;
		
		/** 
		*  The PHP session id 
		*  @property {String} _sessionId
		*  @protected
		*/
		protected $_sessionId;
		
		/** 
		*  The current ip address 
		*  @property {String} _ipAddress
		*  @protected
		*/
		protected $_ipAddress;
		
		/**
		*  Constructor for User
		*/	
		public function __construct()
		{
			session_start();
			
			$this->_user = new User;
			
			// Set all the login to default values
			$this->clear();		
		
			// Create the new session data 
			$this->_sessionId = session_id();
			$this->_ipAddress = ifsetor($_SERVER['REMOTE_ADDR']);
			$this->_domain = ifsetor($_SERVER['SERVER_NAME']);
			$cookie = ifsetor($_COOKIE[$this->cookieName]);
			
			// Login with session data
			if (ifsetor($_SESSION['loggedin'], false)) 
			{
				$this->checkSession();
			}
			// Login using cookies 
			else if ($cookie)
			{
				$this->checkRemembered($cookie);
			}
			
			// Define all of the user constants
			define('LOGGED_IN', $this->_loggedin);
			define('USER_FULLNAME', $this->_user->fullname);
			define('USER_EMAIL', $this->_user->email);
			define('USER_ID', $this->_user->id);
			define('USER_USERNAME', $this->_user->username);
			define('USER_PRIVILEGE', $this->_user->privilege);
			define('USER_LOGIN', $this->_user->login);
			define('USER_HASH', $this->_user->password);
		}
		
		/**
		*  Override getter 
		*/
		public function __get($name)
		{
			/**
			*  Get the user data properties
			*  @property {Dictionary} settings
			*  @readOnly
			*/
			if ($name == 'settings')
			{
				return array(
					'loggedIn' => $this->_loggedin,
					'userFullname' => $this->_user->fullname,
					'userEmail' => $this->_user->email,
					'userId' => $this->_user->id,
					'userUsername' => $this->_user->username,
					'userPrivilege' => $this->_user->privilege,
					'userLogin' => $this->_user->login
				);
			}
			return parent::__get($name);
		}
	
		/**
		*  The logout function terminates session
		*  and eliminates all session data
		*  cookie is also cleared
		*  @method logout
		*/
		public function logout()
		{		
			// Remove sessions that have expired
			$this->service('users')->clearExpiredSessions(
				$this->_user->id, 
				$this->loginExpires
			);
		
			//unset cookie
			setcookie(
				$this->cookieName, 
				false, 
				time() - $this->loginExpires, '/', $this->_domain, 
				false
			);
				
			//clear user's information
			$this->clear();
		
			//wipe out session variables
			unset($_SESSION);
		
			if (session_name())
			{
				//destory session		
				session_destroy();

				// But we do want a session started for the next request
				session_start();
				session_regenerate_id();
			}
		}
	
		/**
		*  Queries database for entry matching
		*  username and password. Password needs
		*  to be sent in as hash value.
		*  Remember is a boolean to save cookie or not
		*  @method login
		*  @param {String} username The username
		*  @param {String} password The password
		*  @param {Boolean} remember If we're suppose to remember this
		*  @param {Boolean} [isPasswordHashed=false] If the password we're supplying is the hashed password
		*  @return {Boolean} If the user has been logged in
		*/
		public function login($username, $password, $remember, $isPasswordHashed=false)
		{		
			// Can't process with empty fields
			if ( !$username || !$password ) 
			{
				$this->error = self::ERR_EMPTY;
				$this->logout();
				return false;
			}
		
			// First we check for frozen time
			// from user maxing out login attempts
			$isValidUser = false;
			
			// Get the user by this username
			$result = $this->service('users')->getUserByLogin($username);
			
			if ($result)
			{
				if (!$result->isActive)
				{
					// Check to see if user is an deactivated
					$this->error = self::ERR_DEACTIVATED;
					return false;
				}
				
				$frozen = $result->frozen;
				$attempts = $result->attempts;
				$isValidUser = true;
			
				if (strtotime($frozen) - strtotime('now') > 0) 
				{
					$this->error = self::ERR_FROZEN;
					$this->logout();
					return false;
				}
				// Check for attempt timeouts
				if ($attempts == $this->loginAttempts)
				{
					$this->error = self::ERR_FROZEN;
					$this->service('users')->freezeUsername($username, $this->frozenMinutes);
					return false;
				}
			}
		
			// After all that crap, 
			// lets finally check username & password
			if ($user = $this->service('users')->checkLogin($username, $password, $isPasswordHashed))
			{
				$this->setSession($user, $remember);
				return true;
			}
			else if ($isValidUser) 
			{   
				// Wrong password for user
				$attempts++;
				$this->error = self::ERR_PASS . $attempts . ' of ' . $this->loginAttempts . ' for ' . $username;
				$this->service('users')->reportAttempt($username);
			}
			else 
			{
				// Login completely fails
				$this->error = self::ERR_WRONG;
			}
			$this->logout();
			return false;  
		}
	
		/**
		*  Creates all the session data and assigns user's data to class variables
		*  @method setSession
		*  @private
		*  @param {User} user The User object
		*  @param {Boolean} remember If we should remember this login
		*  @param {Boolean} [init=true] If this is the initial login
		*/
		private function setSession(User $user, $remember, $init=true)
		{			
			$this->_loggedin = true;
			$this->_user = $user;
			
			// Set session variables
			$_SESSION['username'] = $this->_user->username;
			$_SESSION['password'] = $this->_user->password;
			$_SESSION['id'] = $this->_user->id;
			$_SESSION['loggedin'] = true;
			
			// Don't keep updating the login time
			if (!isset($_SESSION['login']))
			{
				$_SESSION['login'] = $this->_user->login;
			}
			
			// If we want to remember this session for later
			// we'll create a cook we can access for the user
			if ($remember)
			{
				$this->updateCookie($this->_user->id);
			}
			
			// If this is the first time we're setting a session
			// then we'll create a new session
			if ($init)
			{
				$this->service('users')->createSession(
					$this->_user->id, 
					$this->_sessionId, 
					$this->_ipAddress
				);
			}
			// else we're refreshing the user login time
			else
			{  
				$this->service('users')->refresh($this->_user->id);
			} 
		}
	
		/**
		*  Validates user based on existing session information
		*  if no user exists, kill session and logout.
		*  @method checkSession
		*  @private
		*/
		private function checkSession()
		{			
			$user = $this->service('users')->checkSession(
				ifsetor($_SESSION['username']), 
				ifsetor($_SESSION['password']), 
				$this->_sessionId, 
				$this->_ipAddress
			);
			
			if ($user)
			{
				$this->setSession($user, false, false);
			} 
			else 
			{
				$this->logout();
			} 
		} 
	
		/**
		*  Takes cookie data and varifies user
		*  terminate session if no user found
		*  @method checkRemembered
		*  @private
		*  @param {String} cookie The cookie to get the login from
		*/
		private function checkRemembered($cookie)
		{
			list($userId, $sessionId) = explode(':', stripslashes($cookie));
			
			if (!$userId || !$sessionId) return;

			// Check the cookie credientials against the saved sessions
			$user = $this->service('users')->checkCookieLogin($userId, $sessionId);
			
			if ($user)
			{				
				// Update the session id locally
				session_id($sessionId);
				$this->_sessionId = $sessionId;
				
				// Finally auto-login the user
				$this->login($user->username, $user->password, false, true);
			}
		}
	
		/**
		*  Create the cookie to be remembered later, if the user updates their password
		*  make sure you update the cookie
		*  @method updateCookie
		*  @param {int} userId The user ID
		*/
		public function updateCookie($userId)
		{
			setcookie(
				$this->cookieName, 
				$userId.':'.$this->_sessionId, 
				time() + $this->loginExpires, "/", 
				$this->_domain, 
				false
			);
		}
		
		/**
		*  Update the user password
		*  @method updatePassword
		*  @param {String} password The plain text password (not hashed)
		*  @return {Boolean} If password was set successfully
		*/
		public function updatePassword($password)
		{
			if (!LOGGED_IN) return false;
			
			$hash = PasswordUtils::hash($password);
			$result = $this->service('users')->updateUser(USER_ID, 'password', $hash);
			
			if ($result)
			{
				$_SESSION['password'] = $hash;
				$this->updateCookie(USER_ID);
				return true;
			}
			else
			{
				return false;
			}
		}
	
		/**
		*  Defaults for all the user's relevant data
		*  @method clear
		*  @private
		*/
		private function clear()
		{
			$this->_user->username = null;
			$this->_user->password = null;
			$this->_user->fullname = null; 
			$this->_user->email = null;
			$this->_user->id = -1; 
			$this->_user->privilege = Privilege::ANONYMOUS;
			$this->_user->login = null; 
			$this->_loggedin = false;
		}   
	}
}