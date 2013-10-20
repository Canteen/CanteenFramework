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
			
			// Add the client and render fields to config
			$db->execute("ALTER TABLE  `config` ADD  `access` TINYINT( 1 ) UNSIGNED NOT NULL DEFAULT  '0'");
			
			$db->update('config')->set('access', SETTING_CLIENT)->where("`name`='siteIndex'")->result();
			$db->update('config')->set('access', SETTING_WRITE | SETTING_RENDER)->where("`name`='siteTitle'")->result();
			$db->update('config')->set('access', SETTING_WRITE | SETTING_CLIENT)->where("`name`='clientEnabled'")->result();
			$db->update('config')->set('access', 0)->where("`name`='dbVersion'")->result();
			$db->update('config')->set('access', SETTING_WRITE)->where("`name`='templatePath'")->result();
			$db->update('config')->set('access', SETTING_WRITE)->where("`name`='contentPath'")->result();
			
			// Return the new version of the database
			echo 103;
		}
	}
	new DatabaseUpdate102();
}