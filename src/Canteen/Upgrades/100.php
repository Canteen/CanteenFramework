<?php
	
namespace Canteen\Upgrades
{
	use Canteen\Utilities\CanteenBase;
	
	/**
	*  Add page-specific caching
	*/
	class DatabaseUpdate100 extends CanteenBase
	{
		public function __construct()
		{
			$db = $this->site->db;
			
			// Add a cache option to the database
			$db->execute("ALTER TABLE  `pages` ADD  `cache` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0' AFTER  `privilege`");
			
			// Return the new version of the database
			echo 101;
		}
	}
	new DatabaseUpdate100();
}