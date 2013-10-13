<?php

/**
*  @module Canteen\Services\Objects
*/
namespace Canteen\Services\Objects
{
	/**
	*  The user data object representing a single user.  Located in the namespace __Canteen\Services\Objects__.
	*  
	*  @class User
	*/
	class User
	{
		/** 
		*  The user id 
		*  @property {int} id
		*/
		public $id;
		
		/** 
		*  The fullname of the user 
		*  @property {String} fullname
		*/
		public $fullname;
		
		/** 
		*  The privilege 
		*  @property {int} privilege
		*/
		public $privilege;
		
		/** 
		*  If the user is an active user 
		*  @property {Boolean} isActive
		*/
		public $isActive;
		
		/** 
		*  The username 
		*  @property {String} username
		*/
		public $username;
		
		/** 
		*  The first name of the user 
		*  @property {String} firstName
		*/
		public $firstName;
		
		/** 
		*  The last name of the user 
		*  @property {String} lastName
		*/
		public $lastName;
		
		/** 
		*  The required email address for the user 
		*  @property {String} email
		*/
		public $email;
		
		/** 
		*  The hashed password for the user 
		*  @property {String} password
		*/
		public $password;
		
		/** 
		*  If the user forgot their password 
		*  @property {String} forgotString
		*/
		public $forgotString;
		
		/** 
		*  The number of failed attempts 
		*  @property {int} attempts
		*/
		public $attempts;
		
		/** 
		*  The timestamp until the user can use their account again 
		*  @property {Date} frozen
		*/
		public $frozen;
		
		/** 
		*  The last time the user logged in 
		*  @property {Date} login
		*/
		public $login;
	}
}