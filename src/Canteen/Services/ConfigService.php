<?php

/**
*  @module Canteen\Services
*/
namespace Canteen\Services
{	
	use Canteen\Authorization\Privilege;
	use Canteen\Utilities\Validate;
	use Canteen\Site;
	
	/**
	*  Interacts with the config data table from the database. Located in the namespace __Canteen\Services__.
	*  
	*  @class ConfigService 
	*  @extends Service
	*/
	class ConfigService extends Service
	{
		/** 
		*  The collection of fields to get on the table 
		*  @property {Array} properties
		*  @private
		*/
		private $properties = array(
			'`config_id` as `id`',
			'`name`',
			'`value`',
			'`value_type` as `type`',
			'`access`'
		);
		
		/** 
		*  The table with the config 
		*  @property {String} table
		*  @private
		*/
		private $table = 'config';
		
		/** 
		*  The name of the config class object 
		*  @property {String} className
		*  @private
		*/
		private $className = 'Canteen\Services\Objects\Config';
		
		/**
		*  Create the service
		*/
		public function __construct()
		{
			parent::__construct('config');
		}
		
		/**
		*  Install the config table
		*  @method install
		*  @param {String} siteTitle The name of the site title
		*  @param {String} contentPath The local path to the page HTML content
		*  @param {String} templatePath The local path to the HTML main template
		*  @return {Boolean} If the table was installed
		*/
		public function install($siteTitle='', $contentPath='assets/html/content/', $templatePath='assets/html/index.html')
		{
			$this->internal('Canteen\Forms\Installer');
			
			if (!$this->db->tableExists($this->table))
			{
				$sql = "CREATE TABLE IF NOT EXISTS `config` (
				  `config_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
				  `name` varchar(64) NOT NULL,
				  `value` varchar(255) NOT NULL,
				  `value_type` set('string','boolean','integer','path','page') NOT NULL DEFAULT 'string',
				  `access` tinyint(1) UNSIGNED NOT NULL DEFAULT  '0',
				  PRIMARY KEY (`config_id`),
				  UNIQUE KEY `name` (`name`)
				) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;";
				
				$result = (bool)$this->db->execute($sql);
				
				if ($result)
				{
					return $this->db->insert($this->table)
						->fields('config_id', 'name', 'value', 'value_type', 'access')
						->values(1, 'siteIndex', 'home', 'page', SETTING_CLIENT)
						->values(2, 'siteTitle', $siteTitle, 'string', SETTING_RENDER | SETTING_WRITE)
						->values(3, 'contentPath', $contentPath, 'path', SETTING_WRITE | SETTING_RENDER)
						->values(4, 'templatePath', $templatePath, 'path', SETTING_WRITE)
						->values(5, 'dbVersion', Site::DB_VERSION, 'integer', 0)
						->values(6, 'clientEnabled', 1, 'boolean', SETTING_CLIENT | SETTING_WRITE)
						->result();
				}
			}
			return false;
		}
		
		/**
		*  Get the collection of values types
		*  @method getValueTypes
		*  @return {Array} A collection of value type strings
		*/
		public function getValueTypes()
		{
			return array(
				'integer', 
				'string', 
				'path', 
				'boolean', 
				'page'
			);
		}
		
		/**
		*  Get all the the config for the site and add them to the settings
		*  @method registerSettings
		*  @return {Dictionary} The collection of all config items 
		*/
		public function registerSettings()
		{
			$this->internal('Canteen\Site');
			
			$result = $this->db->select($this->properties)
				->from($this->table)
				->results(true); // cache
							
			if (!$result) return;
					
			foreach($result as $property)
			{
				$name = (string)$property['name'];
				$type = (string)$property['type'];
				$value = (string)$property['value'];
				$access = (int)$property['access'];
				
				switch($type)
				{
					case 'integer': $value = (int)$value; break;
					case 'boolean': $value = (bool)((int)$value); break;
				}
				
				$this->settings->addSetting($name, $value, $access);
			}
		}
		
		/**
		*  Get all the the config for the site
		*  @method getConfigs
		*  @return {Array} The collection of Config objects
		*/
		public function getConfigs()
		{
			$result = $this->db->select($this->properties)
				->from($this->table)
				->results();
				
			return $this->bindObjects($result, $this->className);
		}
		
		/**
		*  Get a config variable by name
		*  @method getValueByName
		*  @param {String} name The name of the config property
		*  @return {mixed} The value of the config property
		*/
		public function getValueByName($name)
		{
			$this->verify($name, Validate::URI);
			
			$result = $this->db->select('`value`', '`value_type` as `type`')
				->from($this->table)
				->where("`name`='$name'")
				->result();
			
			if ($result)
			{
				$type = $result['type'];
				$value = $result['value'];
				return $type == 'integer' ? (int)$value : $value;
			}
		}
		
		/**
		*  Get a config variable by id
		*  @param {int} id The ID of the config Option
		*  @return {Config} The config object
		*/
		public function getConfigById($id)
		{
			$results = $this->db->select($this->properties)
				->from($this->table)
				->where("`config_id` in ".$this->valueSet($id))
				->results();
				
			return $this->bindObject($results, $this->className);
		}
		
		/**
		*  Add a key to the config
		*  @method addConfig
		*  @param {String} name The key name to set
		*  @param {mixed} value The value of the key to set
		*  @param {String} [valueType='string'] The value type (string or integer)
		*  @param {int} [access=0] The access to the property, see SettingsManager for more
		*         information on controlling access to settings.
		*  @return {int|Boolean} The new ID if successful, false if not
		*/
		public function addConfig($name, $value, $valueType='string', $access=0)
		{
			$this->privilege(Privilege::ADMINISTRATOR);
			$this->verify($valueType, $this->getValueTypes());
			$this->verify($value, $this->getValidationByType($valueType));
			$this->verify($name, Validate::URI);
			
			$id = $this->db->nextId($this->table, 'config_id');
			return $this->db->insert($this->table)
				->values(array(
					'config_id' => $id,
					'name' => $name,
					'value' => $value,
					'value_type' => $valueType,
					'access' => $access
				))
				->result() ? $id : false;
		}
		
		/**
		*  Get the validator by the current type 
		*  @method getValidationByType
		*  @param {String} type The type of config (path, page, boolean, integer, string)
		*  @return {RegExp} The regular expression for validating
		*/
		public function getValidationByType($type)
		{
			switch($type)
			{
				case 'path' :
				case 'page' : return Validate::URI;
				case 'boolean' : return Validate::BOOLEAN;
				case 'integer' : return Validate::NUMERIC;
				default : return Validate::FULL_TEXT;
			}
		}
		
		/**
		*  Update the value by name
		*  @method updateValue
		*  @param {String} name The name of the key to update
		*  @param {mixed} value The value to update to
		*  @return {Boolean} If successfully updated
		*/
		public function updateValue($name, $value)
		{
			$this->internal('Canteen\Site');
			$this->verify($name, Validate::URI);
			$this->verify($value, Validate::FULL_TEXT);
			
			return $this->db->update($this->table)
				->set('value', $value)
				->where("`name`='$name'")
				->result();
		}
		
		/**
		*  Update a config's property or properties
		*  @method updateConfig
		*  @param {int} id The config id
		*  @param {String|Dictionary} prop The property name or an array of property => value
		*  @param {mixed} [value=null] The value to update to if we're updating a single item
		*  @return {Boolean} If successfully updated
		*/
		public function updateConfig($id, $prop, $value=null)
		{
			$this->privilege(Privilege::ADMINISTRATOR);
			$this->verify($id);
			
			if (!is_array($prop))
			{
				$prop = array($prop => $value);
			}
			
			$properties = array();
			foreach($prop as $k=>$p)
			{
				$k = $this->verify($k, Validate::URI);
				$k = $this->convertPropertyNames($k);
				$properties[$k] = $this->verify($p, Validate::FULL_TEXT);
			}
			
			return $this->db->update($this->table)
				->set($properties)
				->where('`config_id`='.$id)
				->result();
		}
		
		/**
		*  Convert the public property names
		*  @method convertPropertyNames
		*  @private
		*  @param {String} prop The field name
		*  @return {String} The field name to add to the database
		*/
		private function convertPropertyNames($prop)
		{
			$props = array(
				'type' => 'value_type'
			);
			return isset($props[$prop]) ? 
				$props[$prop] : 
				$this->verify($prop, Validate::URI);
		}
		
		/**
		*  Delete a config key
		*  @method removeConfig
		*  @param {id|Array} id The config ID or collection of IDs to delete
		*  @return {Boolean} If delete was successful
		*/
		public function removeConfig($id)
		{
			$this->privilege(Privilege::ADMINISTRATOR);
			
			return $this->db->delete($this->table)
				->where("`config_id` in ".$this->valueSet($id))
				->result();
		}
	}
}