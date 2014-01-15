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
		*  Option if the setting should be available to the client, JavaScript
		*  @property {int} CLIENT
		*  @static
		*  @final 
		*/
		const CLIENT = 1;
		
		/**
		*  Option if the setting can be rendered on the page
		*  @property {int} RENDER
		*  @static
		*  @final 
		*/
		const RENDER = 2;
		
		/**
		*  Option if the option can be changed from the config panel
		*  @property {int} WRITE
		*  @static
		*  @final 
		*/
		const WRITE = 4;
		
		/**
		*  Option if the option can be deleted from the config panel
		*  @property {int} DELETE
		*  @static
		*  @final 
		*/
		const DELETE = 8;
		
		/**
		*  Enable all access
		*  @property {int} ALL
		*  @static
		*  @final 
		*/
		const ALL = 15;
		
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
			$this->settings = [];
			$this->settingsMap = [];
			
			define('SETTING_CLIENT', self::CLIENT);
			define('SETTING_RENDER', self::RENDER);
			define('SETTING_DELETE', self::DELETE);
			define('SETTING_WRITE', self::WRITE);
			define('SETTING_ALL', self::ALL);
		}
	
		/**
		*  Add a collection of settings to the settings manager
		*  @method addSettings
		*  @param {Dictionary} settings The associative-array of properties
		*  @param {int} [access=0] The access to enable for access, default to none
		*  @return {StateManager} Make it easier to chain
		*/
		public function addSettings(array $settings, $access=0)
		{
			foreach($settings as $name=>$value)
			{
				$this->addSetting($name, $value, $access);
			}
			return $this;
		}
	
		/**
		*  Add a single setting to the settings manager
		*  @method addSetting
		*  @param {String} name The name of the property add
		*  @param {mixed} value The value of the setting
		*  @param {int} [access=0] The access to enable for access, default to none
		*  @return {StateManager} Make it easier to chain
		*/
		public function addSetting($name, $value, $access=0)
		{
			if (isset($this->settingsMap[$name]))
			{
				throw new CanteenError(CanteenError::SETTING_NAME_TAKEN, $name);
			}
			$setting = new Setting($name, $value, $access);		
			$this->settings[] = $setting;
			$this->settingsMap[$name] = $setting;			
			return $this;
		}
		
		/**
		*  Change the access by name
		*  @param {String} name The name of the setting 
		*  @param {int} access The access to enable for access, default to none
		*  @return {StateManager} Make it easier to chain
		*/
		public function access($name, $access)
		{
			$setting = ifsetor($this->settingsMap[$name]);
			
			if ($setting)
			{
				$setting->access = $access;
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
			$missing = [];
			foreach($keys as $key)
			{
				if (!isset($this->settingsMap[$key]))
				{
					$missing[] = $key;
				}
			}
			return !count($missing);
		}
		
		/**
		*  If multiple settings exist, throw exception if not
		*  @method exists
		*  @public
		*  @param {String} args* The data names that are required
		*  @return {Boolean} If the access check out
		*/
		public function existsThrow($args)
		{
			$keys = is_array($args) ? $args : func_get_args();
			$missing = [];
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
		*  @method getRender
		*  @return {Dictionary} The collection of settings
		*/
		public function getRender()
		{
			$result = [];
			foreach($this->settings as $s)
			{
				if ($s->access & self::RENDER)
				{
					$result[$s->name] = $s->value;
				}
			}
			return $result;
		}
		
		/**
		*  Get the collection of settings that are okay-ed for use by the client
		*  @method getClient
		*  @return {Dictionary} The collection of settings
		*/
		public function getClient()
		{
			$result = [];
			foreach($this->settings as $s)
			{
				if ($s->access & self::CLIENT)
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
				if ($setting->access & self::WRITE)
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
				if ($setting->access & self::DELETE)
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
					throw new CanteenError(CanteenError::SETTING_DELETE, $name);
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
		public $access = 0;
		
		/**
		*  Constructor
		*/
		public function __construct($name, $value, $access)
		{
			$this->name = $name;
			$this->value = $value;
			$this->access = $access;
		}
	}
}