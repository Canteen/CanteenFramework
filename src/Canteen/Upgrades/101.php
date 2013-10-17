<?php
	
namespace Canteen\Upgrades
{
	use Canteen\Site;
	use Canteen\Utilities\CanteenBase;
	
	/**
	*  Add page-specific caching
	*/
	class DatabaseUpdate extends CanteenBase
	{
		public function __construct()
		{
			$db = Site::instance()->db;
			
			// Clear all data
			$db->truncate('users_sessions');

			// Added additional space for the ip address for IPv6 + IPv4 tunneling
			$db->execute("ALTER TABLE  `users_sessions` CHANGE  `ip_address`  `ip_address` VARCHAR( 45 ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL");

			// Remove the update
			$db->execute("ALTER TABLE `users_sessions` DROP `updated`");
			
			// Change the type of the user_id to match the users table
			$db->execute("ALTER TABLE  `users_sessions` CHANGE  `user_id`  `user_id` INT( 10 ) UNSIGNED NOT NULL");
			
			// Change the user_id field to not be unique
			$db->execute("ALTER TABLE  `users_sessions` ADD INDEX (  `user_id` )");
			$db->execute("ALTER TABLE users_sessions DROP PRIMARY KEY");
			$db->execute("ALTER TABLE `users_sessions` ADD PRIMARY KEY(`session_id`)");
			
			// Return the new version of the database
			echo 102;
		}
	}
	new DatabaseUpdate();
}