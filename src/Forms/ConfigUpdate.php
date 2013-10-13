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
		*  @method process 
		*/
		public function process()
		{
			if (isset($_POST['saveButton']))
			{
				$id = $this->verify(ifsetor($_POST['configId']));
				$value = $this->verify(ifsetor($_POST['configValue']), Validate::FULL_TEXT);
				$type = preg_match('/^[0-9]+$/', $value) ? 'integer' : 'string';
				$config = $this->service('config')->getConfigById($id);
				
				if (!$id)
					$this->error("ID is required to save");
					
				if (!$value)
					$this->error("Value is required");
				
				if (!$config)
					$this->error("No valid config matching id");
					
				if (!$this->ifError())
				{
					$properties = array();

					foreach(array('type', 'value') as $p)
					{
						if ($$p != $config->$p) $properties[$p] = $$p;
					}

					if (!count($properties))
					{
						$this->error("Nothing to update");
						return;
					}
					
					if (!$this->service('config')->updateConfig($id, $properties))
					{
						$this->error("Unable to update");
					}
					else
					{
						$this->success("Config property updated");
					}
				}
			}
			else if (isset($_POST['deleteButton']))
			{
				$id = $this->verify(ifsetor($_POST['configId']));
				
				if (!$id)
				{
					$this->error("ID is required to save");
				}	
				else
				{
					if (!$this->service('config')->removeConfig($id))
					{
						$this->error("Unable to remove config");
					}
					else
					{
						redirect('admin/config');
					}
				}
			}
			else if (isset($_POST['addButton']))
			{
				$name = $this->verify(ifsetor($_POST['configName']), Validate::URI);
				$value = $this->verify(ifsetor($_POST['configValue']), Validate::FULL_TEXT);
				$type = preg_match('/^[0-9]+$/', $value) ? 'integer' : 'string';
				
				if (!$name) 
					$this->error("Name is required");
				else if ($this->service('config')->getValueByName($name))
					$this->error("This name '$name' is already taken");
					
				if (!$value) 
					$this->error("Value is required");
					
				if (!$this->ifError())
				{
					if (!$this->service('config')->addConfig($name, $value, $type))
					{
						$this->error("Unable to add new config");
					}
					else
					{
						redirect('admin/config');
					}
				}
			}
		}
	}
}