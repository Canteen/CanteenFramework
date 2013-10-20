<?php

/**
*  @module Canteen\Utilities
*/
namespace Canteen\Utilities
{
	use Canteen\Errors\CanteenError;
	
	class SettingsManager
	{
		/**
		*  The collection of all settings
		*  @property {Array} settings
		*  @private
		*/
		private $settings;
	
		/**
		*  The dictionary map of all settings
		*  @property {Dictionary} settingsMap
		*  @private
		*/
		private $settingsMap;
	
		/**
		*  The settings manager keeps track of all Canteen settings and provides
		*  protection and access control when doing page rendering. All important global
		*  settings are registered here. 
		*  
		*  @class SettingsManager
		*/
		public function __construct()
		{
			$this->settings = array();
			$this->settingsMap = array();
		}
	
		/**
		*  Add a collection of settings to the settings manager
		*  @method addSettings
		*  @param {Dictionary} settings The associative-array of properties
		*  @param {Boolean} [client=false] If the settings are client-facing
		*  @param {Boolean} [renderable=false] If the setting can rendered as part of a template
		*  @param {Boolean} [writeable=false] If the settings value can be changed
		*  @param {Boolean} [deletable=false] If the settings are deletable
		*  @return {StateManager} Make it easier to chain
		*/
		public function addSettings(array $settings, $clientGlobal=false, $renderable=false, $writeable=false, $deletable=false)
		{
			foreach($settings as $name=>$value)
			{
				$this->addSetting($name, $value, $clientGlobal, $renderable, $writeable, $deletable);
			}
			return $this;
		}
	
		/**
		*  Add a single setting to the settings manager
		*  @method addSetting
		*  @param {String} name The name of the property add
		*  @param {mixed} value The value of the setting
		*  @param {Boolean} [client=false] If the settings are client-facing
		*  @param {Boolean} [renderable=false] If the setting can rendered as part of a template
		*  @param {Boolean} [writeable=false] If the settings value can be changed
		*  @param {Boolean} [deletable=false] If the settings are deletable
		*  @return {StateManager} Make it easier to chain
		*/
		public function addSetting($name, $value, $clientGlobal=false, $renderable=false, $writeable=false, $deletable=false)
		{
			if (isset($this->settingsMap[$name]))
			{
				throw new CanteenError(CanteenError::SETTING_NAME_TAKEN, $name);
			}
			$setting = new Setting($name, $value);
			$setting->clientGlobal = $clientGlobal;
			$setting->renderable = $renderable;
			$setting->deletable = $deletable;
			$setting->writeable = $writeable;
		
			$this->settings[] = $setting;
			$this->settingsMap[$name] = $setting;
			
			return $this;
		}
		
		/**
		*  Change the access by name
		*  @param {String} name The name of the setting 
		*  @param {Boolean} [client=false] If the settings are client-facing
		*  @param {Boolean} [renderable=false] If the setting can rendered as part of a template
		*  @param {Boolean} [writeable=false] If the settings value can be changed
		*  @param {Boolean} [deletable=false] If the settings are deletable
		*  @return {StateManager} Make it easier to chain
		*/
		public function access($name, $clientGlobal=false, $renderable=false, $writeable=false, $deletable=false)
		{
			$setting = ifsetor($this->settingsMap[$name]);
			
			if ($setting)
			{
				$setting->clientGlobal = $clientGlobal;
				$setting->renderable = $renderable;
				$setting->deleteable = $deletable;
				$setting->writeable = $writeable;
				return $this;
			}
			else
			{
				throw new CanteenError(CanteenError::INVALID_SETTING, $name);
			}
		}
	
		/**
		*  If multiple settings exist
		*  @method exists
		*  @public
		*  @param {String} args* The data names that are required
		*  @return {Boolean} If the access check out
		*/
		public function exists($args)
		{
			$keys = is_array($args) ? $args : func_get_args();
			$missing = array();
			foreach($keys as $key)
			{
				if (!isset($this->settingsMap[$key]))
				{
					$missing[] = $key;
				}
			}
			
			if (count($missing))
			{
				throw new CanteenError(CanteenError::INVALID_SETTING, implode("', '", $missing));
			}
			return true;
		}
		
		/**
		*  Get the collection of settings that are okay to render through templates
		*  @method getRenderables
		*  @return {Dictionary} The collection of settings
		*/
		public function getRenderables()
		{
			$result = array();
			foreach($this->settings as $s)
			{
				if ($s->renderable)
				{
					$result[$s->name] = $s->value;
				}
			}
			return $result;
		}
		
		/**
		*  Get the collection of settings that write-only
		*  @method getProtectedNames
		*  @return {Array} The collection of setting names
		*/
		public function getProtectedNames()
		{
			$result = array();
			foreach($this->settings as $s)
			{
				if ($s->writeable)
				{
					$result[] = $s->name;
				}
			}
			return $result;
		}
		
		/**
		*  Get the collection of settings that are readOnly
		*  @method getReadOnly
		*  @return {Array} The collection of setting names
		*/
		public function getPrivateNames()
		{
			$result = array();
			foreach($this->settings as $s)
			{
				if (!$s->writeable && !$s->deletable)
				{
					$result[] = $s->name;
				}
			}
			return $result;
		}
	
		/**
		*  Get the collection of settings that are okay-ed for use by the client
		*  @method getClientGlobals
		*  @return {Dictionary} The collection of settings
		*/
		public function getClientGlobals()
		{
			$result = array();
			foreach($this->settings as $s)
			{
				if ($s->clientGlobal)
				{
					$result[$s->name] = $s->value;
				}
			}
			return $result;
		}
	
		/**
		*  Global getter access
		*  @param {String} name The name of the setting
		*/
		public function __get($name)
		{
			if (isset($this->settingsMap[$name]))
			{
				return $this->settingsMap[$name]->value;
			}
			else
			{
				throw new CanteenError(CanteenError::INVALID_SETTING, $name);
			}
		}
	
		/**
		*  Global setter access
		*  @param {String} name The name of the setting
		*  @param {mixed} value The value of the setting
		*/
		public function __set($name, $value)
		{
			$setting = ifsetor($this->settingsMap[$name]);
			if ($setting)
			{
				if ($setting->writeable)
				{
					return $setting->value;
				}
				else
				{
					throw new CanteenError(CanteenError::SETTING_WRITEABLE, $name);
				}
			}
			else
			{
				throw new CanteenError(CanteenError::INVALID_SETTING, $name);
			}
		}
	
		/**
		*  Remove a setting
		*  @param {String} name The name of the setting to remove
		*/
		public function __unset($name)
		{
			$setting = ifsetor($this->settingsMap[$name]);
			if ($setting)
			{
				if ($setting->deletable)
				{
					// Remove from the map
					unset($this->settingsMap[$name]);
				
					// Remove from the settings collection
					foreach($this->settings as $i=>$s)
					{
						if ($s === $setting)
						{
							array_splice($this->settings, $i, 1);
							break;
						}
					}
				}
				else
				{
					throw new CanteenError(CanteenError::SETTING_DELETABLE, $name);
				}
			}
			else
			{
				throw new CanteenError(CanteenError::INVALID_SETTING, $name);
			}
		}
	}
	
	/**
	*  Internal class to 
	*/
	class Setting
	{
		/** The name of the setting */
		public $name;
		
		/** The value of the setting */
		public $value;
		
		/** If the setting is client-facing */
		public $clientGlobal = false;
		
		/** If the setting is deletable */
		public $deletable = false;
		
		/** If the setting is editable */
		public $writable = false;
		
		/** If the setting can be rendered in a template */
		public $renderable = false;
		
		/**
		*  Constructor
		*/
		public function __construct($name, $value)
		{
			$this->name = $name;
			$this->value = $value;
		}
	}
}