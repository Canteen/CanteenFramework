<?php

/**
*  @module Canteen\Controllers
*/
namespace Canteen\Controllers
{
	use Canteen\Utilities\StringUtils;
	
	/**
	*  Handle the rendering of the admin config page.
	*  Located in the namespace __Canteen\Controllers__.
	*  
	*  @class AdminConfigController
	*  @extends AdminController
	*/
	class AdminConfigController extends AdminController
	{
		/** The collection of all pages */
		private $allPages;
		
		/**
		*  Process the controller and build the view
		*  @method process
		*/
		public function process()
		{
			$this->allPages = $this->service('pages')->getPages();
			
			$configs = $this->service('config')->getConfigs();
			$protected = $this->settings->getProtectedNames();
			$private = $this->settings->getPrivateNames();

			// Great the selection options for the value type
			$types = html('option', 'auto', 'selected=selected');
			foreach($this->service('config')->getValueTypes() as $type)
			{
				$types .= html('option', $type);
			}
			
			foreach($configs as $i=>$config)
			{
				// Ignore private properties
				if (in_array($config->name, $private))
				{
					unset($configs[$i]);
					continue;
				}
				$config->isPage = ($config->type == 'page');
				$config->isBool = ($config->type == 'boolean');
				$config->isNormal = !$config->isPage && !$config->isBool;
				
				// Disable protected properties
				if ($config->isPage)
				{
					$config->value = $this->getPages($config->value);
				}
				if ($config->isBool)
				{
					$config->value = $config->value ? 'checked' : '';
				}
				$config->disabled = in_array($config->name, $protected) ? 'disabled' : '';
			}
			
			$this->addTemplate('AdminConfig', array(
				'configs' => $configs,
				'types' => $types
			));
		}
		
		/**
		*  Get the users as options
		*  @method getPages
		*  @private
		*  @param {String} [selectUri=null] Select this optional page uri
		*/
		private function getPages($selectUri=null)
		{
			$options = '';
			foreach($this->allPages as $page)
			{				
				$option = html(
					'option value='.$page->uri, 
					$page->title . ' ('.$page->uri.')'
				);
				
				if ($selectUri == $page->uri)
				{
					$option->selected = 'true';
				}
				$options .= (string)$option;
			}
			return $options;
		}
	}
}