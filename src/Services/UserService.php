<?php

/**
*  @module Canteen\Services
*/
namespace Canteen\Services
{	
	use Canteen\Utilities\Validate;
	use Canteen\Utilities\PasswordUtils;
	use Canteen\Authorization\Privilege;
	
	/**
	*  Services for accessing the Users and logging in to the site.  Located in the namespace __Canteen\Services__.
	*  
	*  @class UserService
	*  @extends Service
	*/
	class UserService extends Service
	{
		/** 
		*  The list of user select table properties 
		*  @property {Array} properties
		*  @private
		*/
		private $properties = array(
			'`user_id` as `id`',
			'IF(`is_active`>0, 1, NULL) as `isActive`',
			'`username`',
			'`email`',
			'`password`',
			'`first_name` as `firstName`',
			'`last_name` as `lastName`',
			'CONCAT(`first_name`,\' \',`last_name`) as `fullname`',
			'`privilege`',
			'`attempts`',
			'UNIX_TIMESTAMP(`frozen`) as `frozen`',
			'`forgot_string` as `forgotString`',
			'UNIX_TIMESTAMP(`login`) as `login`'
		);
		
		/** 
		*  The list of user select table properties for joining tables 
		*  @property {Array} propertiesJoined
		*  @private
		*/
		private $propertiesJoined = array(
			'u.`user_id` as `id`',
			'IF(u.`is_active`>0, 1, NULL) as `isActive`',
			'u.`username`',
			'u.`email`',
			'u.`password`',
			'u.`first_name` as `firstName`',
			'u.`last_name` as `lastName`',
			'CONCAT(u.`first_name`,\' \',u.`last_name`) as `fullname`',
			'u.`privilege`',
			'u.`attempts`',
			'UNIX_TIMESTAMP(u.`frozen`) as `frozen`',
			'u.`forgot_string` as `forgotString`',
			'UNIX_TIMESTAMP(u.`login`) as `login`'
		);
		
		/** 
		*  The table the stores the users 
		*  @property {String} table
		*  @private
		*/
		private $table = 'users';
		
		/** 
		*  The table for who's session is currently active
		*  @property {String} sessionsTable
		*  @private
		*/
		private $sessionsTable = 'users_sessions';
		
		/**
		*  The name of the user data class 
		*  @property {String} className
		*  @private
		*/
		private $className = 'Canteen\Services\Objects\User';
		
		/**
		*  Create the service
		*/
		public function __construct()
		{
			parent::__construct('users');
		}
		
		/**
		*  Install the table for the first time.
		*  @method install
		*  @param {String} username The admin username
		*  @param {String} email The admin email address
		*  @param {String} password The admin password
		*  @param {String} firstName The admin first name
		*  @param {String} lastName The admin last name
		*  @return {Boolean} if successfully installed
		*/
		public function install($username, $email, $password, $firstName, $lastName)
		{
			$this->internal('Canteen\Forms\Installer');
			
			if (!$this->db()->tableExists($this->table))
			{
				$sql = array();
				
				$sql[] = "CREATE TABLE IF NOT EXISTS `{$this->table}` (
				  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
				  `is_active` tinyint(1) unsigned NOT NULL DEFAULT '1',
				  `username` varchar(32) NOT NULL DEFAULT '',
				  `email` varchar(128) NOT NULL,
				  `password` varchar(128) NOT NULL,
				  `first_name` varchar(100) NOT NULL,
				  `last_name` varchar(100) NOT NULL,
				  `privilege` tinyint(1) NOT NULL DEFAULT '0',
				  `attempts` tinyint(1) unsigned NOT NULL DEFAULT '0',
				  `frozen` datetime NOT NULL,
				  `forgot_string` varchar(32) DEFAULT NULL,
				  `login` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				  PRIMARY KEY (`user_id`),
				  UNIQUE KEY `email` (`email`),
				  UNIQUE KEY `username` (`username`),
				  KEY `privilege` (`privilege`),
				  KEY `is_active` (`is_active`)
				) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;";
				
				$sql[] = "CREATE TABLE IF NOT EXISTS `{$this->sessionsTable}` (
				  `user_id` int(10) unsigned NOT NULL,
				  `ip_address` varchar(45) NOT NULL,
				  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				  `session_id` varchar(32) NOT NULL,
				  PRIMARY KEY (`session_id`),
				  KEY `user_id` (`user_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
				
				$success = (bool)$this->db()->execute($sql);
				
				return $this->internalAddUser(
					$username, 
					$email, 
					$password, 
					$firstName, 
					$lastName, 
					Privilege::ADMINISTRATOR
				) && $success;
			}
		}
		
		/**
		*  Clear all expired sessions
		*  @method clearExpiredSessions
		*  @param {int} id User's ID
		*  @param {int} idledSec The number of idled seconds
		*  @return {Boolean} If successful cleared sessions
		*/
		public function clearExpiredSessions($id, $idledSec)
		{
			$now = date(DATE_FORMAT_MYSQL);

			return $this->db()->delete($this->sessionsTable)
				->where(
					"`user_id`='$id'",
					"UNIX_TIMESTAMP('$now')-UNIX_TIMESTAMP(`created`) > $idledSec")
				->result();
		}
		
		/**
		*  Freeze the username after the user has failed-out the max number of login attempts
		*  @method freezeUsername
		*  @param {String} username The username
		*  @param {int} frozenMins Number of minutes to freeze before the user can try again
		*  @return {Boolean} If successful
		*/
		public function freezeUsername($username, $frozenMins)
		{
			$this->internal('Canteen\Authorization\Authorization');
			
			$this->verify($frozenMins);
			$this->verify($username, Validate::ALPHA);
			
			return $this->db()->update($this->table)
				->set('frozen', date(DATE_FORMAT_MYSQL, strtotime("+ $frozenMins minutes")))
				->set('attempts', 0)
				->where("`username`='$username'")
				->result();
		}
		
		/**
		*  The user attempted another failed try to login
		*  @method reportAttempt
		*  @param {String} username The username
		*  @return {Boolean} if successful
		*/
		public function reportAttempt($username)
		{
			$this->internal('Canteen\Authorization\Authorization');
			
			return $this->db()->update($this->table)
				->set('attempts', '`attempts`+1')
				->where("`username`='$username'")
				->result();
		}
		
		/**
		*  Clear the status
		*  @method createSession
		*  @param {int} id The user id
		*  @param {String} sessionId The unique PHP session id
		*  @param {String} ipAddress The ip address of the login
		*  @return {Boolean} If successful 
		*/
		public function createSession($id, $sessionId, $ipAddress)
		{
			$this->internal('Canteen\Authorization\Authorization');
			
			$now = date(DATE_FORMAT_MYSQL);

			// set all attempts to zero, updated frozen time
			$update = $this->db()->update($this->table)
				->set('attempts', 0)
				->set('frozen', $now)
				->set('forgot_string', NULL)
				->where("`user_id`='$id'")
				->result();

			// clear any entries matching the same session id
			$delete = $this->db()->delete($this->sessionsTable)
				->where("`session_id`='$sessionId'")
				->result();

			// create a new entry with new ip, timestamp and session data
			$insert = $this->db()->insert($this->sessionsTable)
				->values(array(
					'ip_address' => $ipAddress,
					'user_id' => $id,
					'session_id' => $sessionId,
					'created' => $now
				))
				->result();

			return $update && $delete && $insert;
		}
		
		/**
		*  Update the last login point on the user's table
		*  @method refresh
		*  @param {int} id The user id
		*  @return {Boolean} if successful
		*/
		public function refresh($id)
		{
			$this->internal('Canteen\Authorization\Authorization');

			return $this->db()->update($this->table)
				->set('login', date(DATE_FORMAT_MYSQL))
				->where("`user_id`='$id'")
				->result();
		}
		
		/**
		*  Check the user's session
		*  @method checkSession 
		*  @param {String} username The username
		*  @param {String} passwordHash The password hash
		*  @param {String} sessionId The unique PHP session ID 
		*  @param {String} ipAddress The ip address of the user
		*  @return {User} The user if successful, null if not
		*/
		public function checkSession($username, $passwordHash, $sessionId, $ipAddress)
		{
			$this->internal('Canteen\Authorization\Authorization');
			
			$results = $this->db()->select($this->propertiesJoined)
				->from(
					$this->sessionsTable.' s', 
					$this->table.' u' 
				)
				->where(
					"u.`is_active`='1'",
					"u.username='$username'",
					"u.password='$passwordHash'",
					"s.session_id='$sessionId'" ,
					"s.ip_address='$ipAddress'",
					's.user_id=u.user_id'
				)
				->results();
				
			return $this->bindObject($results, $this->className);
		}
		
		/**
		*  Check the cookie for authentication
		*  @method checkCookieLogin
		*  @param {int} userId The user's id
		*  @param {String} The saved session ID
		*  @return {User} The user if there's a saved session
		*/
		public function checkCookieLogin($userId, $sessionId)
		{
			$this->internal('Canteen\Authorization\Authorization');
			
			$result = $this->db()->select($this->propertiesJoined)
				->from(
					$this->sessionsTable.' s', 
					$this->table.' u'
				)
				->where(
					"u.`user_id`='$userId'",
					"u.`is_active`='1'",
					"s.`session_id`='$sessionId'",
					's.user_id=u.user_id'
				)
				->results();
				
			return $this->bindObject($result, $this->className);
		}
		
		/**
		*  Check the user login
		*  @method checkLogin
		*  @param {String} usernameOrEmail The username or email address
		*  @param {String} password The text password (can be either plain text or hashed)
		*  @param {Boolean} [isPasswordHashed=false] If the password is hashed
		*  @return {User|Boolean} User object if true, or false if login failed
		*/
		public function checkLogin($usernameOrEmail, $password, $isPasswordHashed=false)
		{
			$this->internal('Canteen\Authorization\Authorization');
			$this->verify($usernameOrEmail, Validate::EMAIL);
			
			$result = $this->db()->select($this->properties)
				->from($this->table)
				->where("(`username`='".$usernameOrEmail."' || 
					`email`='".$usernameOrEmail."')",
					"`is_active`='1'")
				->result();

			if (!$result) return false;

			$user = $this->bindObject($result, $this->className, null, false);

			if ($isPasswordHashed)
			{
				return $password == $user->password ? $user : false;
			}
			else
			{
				return PasswordUtils::validate($password, $user->password) ? $user : false;
			}
		}
		
		/**
		*  Verify that the password has been reset
		*  @method verifyResetPassword
		*  @param {String} username The username
		*  @param {String} The forgot password string
		*  @return {Boolean} If the forgot string is valid
		*/
		public function verifyResetPassword($username, $forgotString)
		{
			$this->internal('Canteen\Forms\ForgotPassword');
			$this->verify($username, Validate::EMAIL);
			$this->verify($forgotString, Validate::URI);
			
			return (bool)$this->db()->select('user_id')
				->from($this->table)
				->where(
					"`username`='$username'",
					"`is_active`='1'",
					"`forgot_string`='$forgotString'"
				)
				->length();
		}
		
		/**
		*  Get a user or user by id
		*  @method getUserById
		*  @param {int|Array} id Either a single user ID or an array of IDs
		*  @return {User|Array} The User object or collection of User objects
		*/
		public function getUserById($id)
		{
			$this->privilege();
			
			$result = $this->db()->select($this->properties)
				->from($this->table)
				->where('`user_id` in '.$this->valueSet($id))
				->results();
			
			// If we only request one user and we have a result, show that
			return is_array($id) ?
			 	$this->bindObjects($result, $this->className):
				$this->bindObject($result, $this->className);
		}
		
		/**
		*  Get a user or users by email addresses or usernames
		*  @method getUser
		*  @param {String|Array} usernameOrEmail Either the username or email address or collection of usernames
		*  @return {User} The User object or collection of User objects
		*/
		public function getUser($usernameOrEmail)
		{
			$this->internal('Canteen\Authorization\Authorization');
			$usernameOrEmail = $this->valueSet($usernameOrEmail, Validate::EMAIL);
			
			$result = $this->db()->select($this->properties)
				->from($this->table)
				->where("`username` in $usernameOrEmail || `email` in $usernameOrEmail")
				->results();
			
			// If we only request one user and we have a result, show that
			return is_array($usernameOrEmail) ?
			 	$this->bindObjects($result, $this->className):
				$this->bindObject($result, $this->className);
		}
		
		/**
		*  Get all of the current users
		*  @method getUsers
		*  @return {Array} A collection of User objects
		*/
		public function getUsers()
		{
			$this->privilege();
			
			$result = $this->db()->select($this->properties)
				->from($this->table)
				->results();
			
			return $this->bindObjects($result, $this->className);
		}
		
		/**
		*  Remove a user by an id
		*  @method removeUser
		*  @param {int|Array} id The user id or collection of IDs
		*  @return {Boolean} If successfully deleted
		*/
		public function removeUser($id)
		{
			$this->privilege(Privilege::ADMINISTRATOR);
			
			return $this->db()->delete($this->table)
				->where('`user_id` in '.$this->valueSet($id))
				->result();	
		}
		
		/**
		*  Add a user to the data base
		*  @method adduser
		*  @param {String} username The username
		*  @param {String} email The email address
		*  @param {String} password The unhashed plain password
		*  @param {String} firstName The first name
		*  @param {String} lastName The last name
		*  @param {int} privilege The privilege
		*  @return {int|Boolean} If successfully return a new ID, or else false
		*/
		public function addUser($username, $email, $password, $firstName, $lastName, $privilege)
		{
			$this->privilege(Privilege::ADMINISTRATOR);
			
			return $this->internalAddUser($username, $email, $password, $firstName, $lastName, $privilege);
		}
		
		/**
		*  Internal method for adding a user
		*  @method internalAddUser
		*  @private
		*  @param {String} username The username
		*  @param {String} email The email address
		*  @param {String} password The unhashed plain password
		*  @param {String} firstName The first name
		*  @param {String} lastName The last name
		*  @param {int} privilege The privilege
		*  @return {int|Boolean} If successfully return a new ID, or else false
		*/
		private function internalAddUser($username, $email, $password, $firstName, $lastName, $privilege)
		{
			$id = $this->db()->nextId($this->table, 'user_id');
			
			return $this->db()->insert($this->table)
				->values(array(
					'user_id' => $id,
					'username' => $this->verify($username, Validate::ALPHA),
					'email' => $this->verify($email, Validate::EMAIL),
					'password' => PasswordUtils::hash($password),
					'first_name' => $this->verify($firstName, Validate::NAMES),
					'last_name' => $this->verify($lastName, Validate::NAMES),
					'privilege' => $this->verify($privilege),
					'is_active' => 1
				))
				->result() ? $id : false;
		}
		
		/**
		*  Update a user property or properties
		*  @method updateUser
		*  @param {int} id The user id
		*  @param {String|Dictionary} prop The property name or an array of property => value
		*  @param {mixed} [value=null] The value to update to if we're updating a single property
		*  @return {Boolean} If successful
		*/
		public function updateUser($id, $prop, $value=null)
		{
			$this->privilege(Privilege::ADMINISTRATOR);
			$this->verify($id);
			
			if (!is_array($prop))
			{
				$prop = array($prop => $value);
			}
			
			$properties = array();
			foreach($prop as $k=>$p)
			{
				$k = $this->verify($k, Validate::URI);
				$k = $this->convertPropertyNames($k);
				
				$type = null;
				switch($k)
				{
					case 'login' : 
					case 'frozen' : $type = Validate::MYSQL_DATE; 
						break;
					case 'forgot_string' : $type = Validate::URI; 
						break;
					case 'username' : $type = Validate::ALPHA; 
						break;
					case 'password' : $type = Validate::FULL_TEXT;
						break;
					case 'email' : $type = Validate::EMAIL;
						break;
					case 'first_name' :
					case 'last_name' : $type = Validate::NAMES; 
						break;
				}
				
				$properties[$k] = $this->verify($p, $type);
			}
			
			return $this->db()->update($this->table)
				->set($properties)
				->where('`user_id`='.$id)
				->result();
		}
		
		/**
		*  Convert the public property names into table field names
		*  @method convertPropertyNames
		*  @private
		*  @param {String} prop The name of the public property
		*  @return {String} The database field name
		*/
		private function convertPropertyNames($prop)
		{
			$props = array(
				'isActive' => 'is_active',
				'firstName' => 'first_name',
				'lastName' => 'last_name',
				'id' => 'user_id',
				'fullname' => 'CONCAT(`first_name`,\' \',`last_name`) as `fullname`',
				'forgotString' => 'forgot_string'
			);
			return isset($props[$prop]) ? 
				$props[$prop] : 
				$this->verify($prop, Validate::URI);
		}
	}
}