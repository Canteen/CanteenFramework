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
	*  @extends SimpleObjectService
	*/
	class ConfigService extends SimpleObjectService
	{	
		/**
		*  Create the service
		*/
		public function __construct()
		{
			parent::__construct(
				'config', 
				'Canteen\Services\Objects\Config',
				'config',
				array(
					$this->field('config_id', Validate::NUMERIC, 'id')
						->setDefault(),
					$this->field('name', Validate::URI)
						->setIndex(),
					$this->field('value', Validate::FULL_TEXT),
					$this->field('value_type', $this->getValueTypes(), 'type'),
					$this->field('access', Validate::NUMERIC)
				)
			);
			
			$this->restrict(
				array(
					'addConfig' => Privilege::ADMINISTRATOR,
					'setup' => 'Canteen\Forms\Installer',
					'updateValue' => 'Canteen\Site',
					'registerSettings' => 'Canteen\Site',
					'updateConfig' => Privilege::ADMINISTRATOR,
					'removeConfig' => Privilege::ADMINISTRATOR,
					'registerSettings' => Privilege::ANONYMOUS
				)
			);
		}
		
		/**
		*  Install the config table
		*  @method setup
		*  @param {String} siteTitle The name of the site title
		*  @param {String} contentPath The local path to the page HTML content
		*  @param {String} templatePath The local path to the HTML main template
		*  @return {Boolean} If the table was installed
		*/
		public function setup($siteTitle, $contentPath, $templatePath)
		{
			$this->access();
			
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
				) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1";
				
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
			$this->access();

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
		*  Add a key to the config
		*  @method addConfig
		*  @param {Dictionary|String} propertiesOrName The collection of properties or name
		*  @param {mixed} [value=''] The value of the config
		*  @param {String} [type='string'] The value type
		*  @param {int} [access=0] The access type
		*  @return {int|Boolean} The new ID if successful, false if not
		*/
		public function addConfig($propertiesOrName, $value='', $type='string', $access=0)
		{
			$properties = $propertiesOrName;

			if (!is_array($properties))
			{
				$properties = array(
					'name' => $properties,
					'value' => $value,
					'type' => $type,
					'access' => $access
				);
			}

			// Specific type validation
			$type = ifsetor($properties['type']);
			$value = ifsetor($properties['value']);
			$this->verify($value, $this->getValidationByType($type));

			return $this->call($properties);
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
		*  Get a config variable by name
		*  @method getValueByName
		*  @param {String} name The name of the config property
		*  @return {mixed} The value of the config property
		*/
		public function getValueByName($name)
		{
			$this->verifyName($name);

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
		*  Get all the the config for the site
		*  @method getConfigs
		*  @return {Array} The collection of Config objects
		*/
		public function getConfigs()
		{
			return $this->call();
		}

		/**
		*  Get a config variable by id
		*  @method getConfig
		*  @param {int} id The ID of the config Option
		*  @return {Config} The config object
		*/
		public function getConfig($id)
		{
			return $this->call($id);
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
			return $this->call($id, $prop, $value);
		}
		
		/**
		*  Delete a config key
		*  @method removeConfig
		*  @param {id|Array} id The config ID or collection of IDs to delete
		*  @return {Boolean} If delete was successful
		*/
		public function removeConfig($id)
		{
			return $this->call($id);
		}
	}
}