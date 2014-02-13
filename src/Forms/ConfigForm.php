<?php

/**
*  @module Canteen\Forms
*/
namespace Canteen\Forms
{
	use Canteen\Utilities\Validate;
	use Canteen\Events\ObjectFormEvent;
	
	/**
	*  The form to handle configuration updating or adding.  Located in the namespace __Canteen\Forms__.
	*  @class Config
	*  @extends ObjectForm
	*/
	class ConfigForm extends ObjectForm
	{
		/**
		*  Process the form and handle the $_POST data.
		*/
		public function __construct()
		{
			$this->updateRedirect = false;

			$this->on(ObjectFormEvent::BEFORE_REMOVE, [$this, 'onBeforeRemove'])
				->on(ObjectFormEvent::BEFORE_ADD, [$this, 'onBeforeAdd'])
				->on(ObjectFormEvent::BEFORE_UPDATE, [$this, 'onBeforeUpdate']);

			parent::__construct(
				$this->service('config')->item,
				$this->settings->uriRequest
			);
		}
		
		/**
		*  Delete a config variable 
		*  @method onBeforeRemove
		*  @param {ObjectFormEvent} event The before remove event
		*/
		public function onBeforeRemove(ObjectFormEvent $event)
		{			
			if (!(SETTING_DELETE & $event->object->access) || !(SETTING_WRITE & $event->object->access))
			{
				$this->error('This property cannot be deleted');
			}
		}
		
		/**
		*  Do some checking before updating
		*  @method onBeforeUpdate
		*  @param {ObjectFormEvent} event The before update event
		*/
		public function onBeforeUpdate(ObjectFormEvent $event)
		{
			if ($event->object->type == 'boolean')
			{
				$_POST['value'] = ifsetor($_POST['value'], 0);
			}
		}

		/**
		*  Delete a config variable 
		*  @method onBeforeAdd
		*  @param {ObjectFormEvent} event The before add event
		*/
		public function onBeforeAdd(ObjectFormEvent $event)
		{
			$name = $this->verify(ifsetor($_POST['name']), Validate::URI);
			$value = ifsetor($_POST['value']);
			
			$client = (int)ifsetor($_POST['client'], 0);
			$render = (int)ifsetor($_POST['render'], 0);
			
			// All config properties submitted here need to be 
			// writable and deleteable
			$_POST['access'] = $client | $render | SETTING_WRITE | SETTING_DELETE;
			
			// Get the config type
			$type = ifsetor($_POST['type'], 'auto');			
			
			if ($type == 'page')
			{
				$page = $this->service('page')->getPageByUri($value);
				if (!$page)
				{
					$this->error('Not a valid page URI stub');
				}
			}
			else if ($type == 'auto') 
			{
				$type = $this->autoDetectType($value);
				$_POST['type'] = $type;
			}
			
			// Validate types
			if (!$name)
			{
				$this->error('Name is required');
			}
			else if ($this->item->service->getValueByName($name))
			{
				$this->error("This name '$name' is already taken");
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