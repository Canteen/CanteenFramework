<?php

/**
*  @module Canteen\Utilities
*/
namespace Canteen\Utilities
{
	/**
	*  Utilities for managing the user password.  Located in the namespace __Canteen\Utilities__.
	*  
	*  @class PasswordUtils
	*/
	class PasswordUtils
	{
		/**
		*  Takes a password and returns the salted hash
		*  @method hash
		*  @static
		*  @param {String} password The plain text password to hash
		*  @return {String} The hash of the password (128 hex characters)
		*/
		public static function hash($password)
		{
			$salt = bin2hex(mcrypt_create_iv(32, MCRYPT_DEV_URANDOM)); //get 256 random bits in hex
			$hash = hash('sha256', $salt . $password); //prepend the salt, then hash
			//store the salt and hash in the same string, so only 1 DB column is needed
			$final = $salt . $hash; 
			return $final;
		}

		/**
		*  Validates a password
		*  returns true if hash is the correct hash for that password
		*  @method validate
		*  @static
		*  @param {String} password The plain text password to check
		*  @param {String} correctHash The password hash to compare against (from database)
		*  @return {Boolean} If the password is valid, false otherwise.
		*/
		public static function validate($password, $correctHash)
		{
			$salt = substr($correctHash, 0, 64); //get the salt from the front of the hash
			$validHash = substr($correctHash, 64, 64); //the SHA256
			$testHash = hash('sha256', $salt . $password); //hash the password being tested
			//if the hashes are exactly the same, the password is valid
			return $testHash === $validHash;
		}
	}
}