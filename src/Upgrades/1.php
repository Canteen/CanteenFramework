<?php

namespace Canteen\Upgrades
{	
	/**
	*  Add a dbVersion config to the database
	*/
	class DatabaseUpdate extends CanteenBase
	{
		public function process()
		{
			// This is a test example of updating 
			// the database structure from earlier version
			$this->service('config')->addConfig('dbVersion', 100, 'integer');
			
			// Return the new version of the database
			return 100;
		}
	}
	
	$update = new DatabaseUpdate();
	echo $update->process();
}