<?php

namespace Canteen\Upgrades
{
	use Canteen\Utilities\CanteenBase;
	
	/**
	*  Add a dbVersion config to the database
	*/
	class DatabaseUpdate1 extends CanteenBase
	{
		public function __construct()
		{
			// This is a test example of updating 
			// the database structure from earlier version
			$this->service('config')->addConfig('dbVersion', 100, 'integer');
			
			// Return the new version of the database
			echo 100;
		}
	}
	new DatabaseUpdate1();
}