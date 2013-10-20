<?php

/**
*  @module Canteen\Forms
*/
namespace Canteen\Forms
{
	use Canteen\Utilities\Validate;
	
	/**
	*  The form to handle configuration updating or adding.  Located in the namespace __Canteen\Forms__.
	*  @class ConfigUpdate
	*  @extends FormBase
	*/
	class ConfigUpdate extends FormBase
	{
		/**
		*  Process the form and handle the $_POST data.
		*/
		public function __construct()
		{			
			if (isset($_POST['saveButton']))
			{
				$this->save();
			}
			else if (isset($_POST['deleteButton']))
			{
				$this->delete();
			}
			else if (isset($_POST['addButton']))
			{
				$this->add();
			}
		}
		
		/**
		*  Update the config value 
		*/
		private function save()
		{	
			$id = $this->verify(ifsetor($_POST['configId']));
			$config = $this->service('config')->getConfigById($id);
			$value = $this->verify(
				ifsetor($_POST['configValue']), 
				$this->service('config')->getValidationByType($config->type)
			);
			
			if (!$id)
				$this->error('ID is required to save');
			
			if (!$config)
				$this->error('No valid config matching id');
				
			if (!$this->ifError)
			{
				$properties = array();

				foreach(array('value') as $p)
				{
					if ($$p != $config->$p) $properties[$p] = $$p;
				}

				if (!count($properties))
				{
					$this->error('Nothing to update');
					return;
				}
				
				if (!$this->service('config')->updateConfig($id, $properties))
				{
					$this->error('Unable to update');
				}
				else
				{
					$this->success('Config property updated');
				}
			}
		}
		
		/**
		*   Delete a config variable 
		*/
		private function delete()
		{
			$id = $this->verify(ifsetor($_POST['configId']));
			
			if (!$id)
			{
				$this->error('ID is required to save');
				return;
			}
			
			$config = $this->service('config')->getConfigById($id);
			
			if (!$config)
			{
				$this->error('No valid config found');
				return;
			}
			
			$protected = $this->settings->getProtectedNames();
			$private = $this->settings->getPrivateNames();
			
			if (in_array($config->name, $protected) || in_array($config->name, $private))
			{
				$this->error('This property cannot be deleted');
				return;
			}
			
			if (!$this->ifError)
			{
				if (!$this->service('config')->removeConfig($id))
				{
					$this->error('Unable to remove config');
				}
				else
				{
					redirect('admin/config');
				}
			}
		}
		
		/**
		*  Add a new custom property 
		*/
		private function add()
		{
			$name = $this->verify(ifsetor($_POST['configName']), Validate::URI);
			$value = ifsetor($_POST['configValue']);
			
			// Get the config type
			$type = ifsetor($_POST['configType'], 'auto');				
			
			if ($type == 'page')
			{
				$page = $this->service('pages')->getPageByUri($value);
				if (!$page)
				{
					$this->error('Not a valid page URI stub');
				}
			}
			else
			{
				// Autodetect the value type
				if ($type == 'auto') $type = $this->autoDetectType($value);

				// verify the value type
				$type = $this->verify($type, $this->service('config')->getValueTypes());
				
				$value = $this->verify($value, 
					$this->service('config')->getValidationByType($type));
			}
			
			// Validate types
			if (!$name) 
				$this->error('Name is required');
				
			else if ($this->service('config')->getValueByName($name))
				$this->error("This name '$name' is already taken");
				
			if (!$this->ifError)
			{
				if (!$this->service('config')->addConfig($name, $value, $type))
				{
					$this->error('Unable to add new config');
				}
				else
				{
					redirect('admin/config');
				}
			}
		}
		
		/**
		*  Auto detect the value type 
		*  @method autoDetectType
		*  @private
		*  @param {String} value The value to check
		*  @return {String} The value type (boolean, string, integer)
		*/
		private function autoDetectType(&$value)
		{
			if ($value == 'true') 
			{
				$value = 1;
				return 'boolean';
			}
			else if ($value == 'false')
			{
				$value = 0;
				return 'boolean';
			}
			else if ($value == '1' || $value == '0')
			{
				$value = (bool)$value;
				return 'boolean';
			}
			else if (preg_match('/^[0-9]+$/', $value))
			{
				return 'integer';
			}
			else
			{
				return 'string';
			}
		}
	}
}