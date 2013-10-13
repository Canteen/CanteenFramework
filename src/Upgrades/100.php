<?php
	
namespace Canteen\Upgrades;
{
	/**
	*  Add page-specific caching
	*/
	class DatabaseUpdate extends CanteenBase
	{
		public function process()
		{
			$db = Site::instance()->getDB();
			
			// Add a cache option to the database
			$db->execute("ALTER TABLE  `pages` ADD  `cache` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0' AFTER  `privilege`");
			
			// Return the new version of the database
			return 101;
		}
	}
	
	$update = new DatabaseUpdate();
	echo $update->process();
}