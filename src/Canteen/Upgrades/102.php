<?php

namespace Canteen\Upgrades
{
	use Canteen\Utilities\CanteenBase;
	
	/**
	*  Add page-specific caching
	*/
	class DatabaseUpdate102 extends CanteenBase
	{
		public function __construct()
		{
			$db = $this->site->db;
			
			// Add a cache option to the database
			$db->execute("ALTER TABLE  `config` CHANGE  `value_type`  `value_type` SET(  'string',  'path',  'boolean',  'integer',  'page' ) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT  'string'");
			
			// Return the new version of the database
			echo 103;
		}
	}
	new DatabaseUpdate102();
}