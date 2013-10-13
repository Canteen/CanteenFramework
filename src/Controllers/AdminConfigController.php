<?php

/**
*  @module Canteen\Controllers
*/
namespace Canteen\Controllers
{
	/**
	*  Handle the rendering of the admin config page.
	*  Located in the namespace __Canteen\Controllers__.
	*  
	*  @class AdminConfigController
	*  @extends AdminController
	*/
	class AdminConfigController extends AdminController
	{
		/**
		*  Process the controller and build the view
		*  @method process
		*/
		public function process()
		{
			$configs = $this->service('config')->getConfigs();
			$protected = $this->service('config')->getProtectedNames();
			
			foreach($configs as $i=>$config)
			{
				$configs[$i]->disabled = in_array($config->name, $protected) ? 'disabled' : '';
			}
			
			$this->addTemplate('AdminConfig', array('configs' => $configs));
		}
	}
}