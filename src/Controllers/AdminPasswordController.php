<?php

/**
*  @module Canteen\Controllers
*/
namespace Canteen\Controllers
{
	/**
	*  Controller for the password change page.
	*  Located in the namespace __Canteen\Controllers__.
	*  
	*  @class AdminPasswordController
	*  @extends AdminController
	*/
	class AdminPasswordController extends AdminController
	{
		/**
		*  Process the controller and build the view
		*  @method process
		*/
		public function process()
		{
			$this->addTemplate('AdminPassword');
		}
	}
}