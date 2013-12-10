<?php

namespace Canteen\Services
{
	use Canteen\Authorization\Privilege;
	use Canteen\Errors\CustomServiceError;

	class CustomService extends Service
	{
		/**
		*  The name of the table of the custom type
		*  @property {String} table
		*  @protected
		*/
		protected $table;

		/**
		*  The name of the class to bind with
		*  @property {String} className
		*  @protected
		*/
		protected $className;

		/**
		*  The name of the single item to use for dynamic method calls
		*  @property {String} singleItem
		*  @protected
		*/
		protected $singleItemName;

		/**
		*  The name of multiple items to use for dynamic class
		*  @property {String} pluralItemName
		*  @protected
		*/
		protected $pluralItemName;

		/**
		*  The collection of CustomField objects
		*  @property {Array} _fields
		*  @private
		*/
		private $_fields;

		/**
		*  The main field, default
		*  @property {CustomField} _defaultField
		*  @private
		*/
		private $_defaultField = null;

		/**
		*  The map of CustomField objects to their names
		*  @property {Dictionary} _fieldsByName
		*  @private
		*/
		private $_fieldsByName = array();

		/**
		*  The property prepend mappings
		*  @property {Dictionary} _mappings
		*  @private
		*/
		private $_mappings = array();

		/**
		*  The collection of mysql select properties
		*  @property {Array} _properties
		*  @private
		*/
		private $_properties = array();

		/**
		*  The map of field names that can be indexed
		*  @property {Dictionary} _indexes
		*  @private
		*/
		private $_indexes = array();

		/**
		*  The CustomService class is an easy way to do custom
		*  data types. 
		*  @class CustomService
		*  @constructor
		*  @param {String} alias The name of the service alias
		*  @param {String} className Class to bind database result to
		*  @param {Array} field The collection of CustomField objects
		*/
		public function __construct($alias, $className, $fields)
		{
			parent::__construct($alias);

			$this->_fields = $fields;
			$this->className = $className;

			// The defaults for table, single, plural
			$this->table = $alias;
			$this->singleItemName = ucfirst($this->defaultSingleName());
			$this->pluralItemName = ucfirst($this->singleItemName . 's');

			foreach($fields as $f)
			{
				if ($f->prependMap)
				{
					$this->_mappings[$f->name] = $f->prependMap;
				}
				$this->_fieldsByName[$f->name] = $f;
				$this->_properties[] = $f->select;
				if ($f->isIndex)
				{
					$this->_indexes[$f->name] = $f;
				}
				if ($f->isDefault)
				{
					$this->_defaultField = $f;
				}
			}
		}

		/**
		*  Get the select properties
		*  @method properties
		*  @protected
		*  @param {Array|String*} [props=null] N-number of strings to set as additional properties,
		*     or a collection of strings to add to the existing properties.
		*  @return {Array} The collection of string used for db selecting
		*/
		protected function properties($props=null)
		{
			if ($props !== null)
			{
				$props = is_array($props) ? $props : func_get_args();
				$this->_properties = array_merge($this->_properties, $props);
			}
			return $this->_properties;
		}

		/**
		*  Get or set the collection of mappings
		*  @method mappings
		*  @protected
		*  @param {Dictionary|String} [maps=null] If null, returns mappings Dictionary
		*  @param {String} [value=null] The value if setting a single map
		*  @return {Dictionary} The mappings
		*/
		protected function mappings($maps=null, $value=null)
		{
			if ($maps !== null)
			{
				// API for setting a single item
				// where maps is the property name
				// and value is the value
				if (is_string($maps) && $value)
				{
					$maps = array($maps => $value);
				}
				$this->_mappings = array_merge($this->_mappings, $maps);
			}
			return $this->_mappings;
		}
		
		/**
		*  Convenience function for creating a new field
		*  @method field
		*  @protected
		*  @param {String} id The name of the field on the database
		*  @param {RegExp|Array} [type=null] The validation type or set of values, default is no validation
		*  @param {String} [name=null] The name of the php property
		*  @return {CustomField} The new custom field object
		*/
		protected function field($id, $type=null, $name=null)
		{
			return new CustomField($id, $type, $name);
		}

		/**
		*  Get the default single name
		*  @method defaultSingleName
		*  @private
		*  @return {String} The name of a single item of this service
		*/
		private function defaultSingleName()
		{
			if ($this->className)
			{
				$class = explode('\\', $this->className);
				return $class[count($class) - 1];
			}
			else
			{
				return preg_replace('/s$/', '', $this->alias);
			}
		}

		/**
		*  Handle dynamic method calls
		*/
		public function __call($method, $arguments=null)
		{
			if ($arguments == null) $arguments = array();

			$internal = null;

			// Check for access control of the method
			$this->access($method);

			$s = $this->singleItemName;
			$p = $this->pluralItemName;

			// Check for function calls where By is called
			if (preg_match_all('/^(get|update|remove)('.$s.'|'.$p.')By([A-Z][A-Za-z]+)$/', $method, $matches))
			{
				$index = $matches[3][0];

				// Lower case the first letter to compare the field name
				$index = strtolower(substr($index, 0, 1)).substr($index, 1);

				if (!isset($this->_indexes[$index]))
				{
					throw new CustomServiceError(CustomServiceError::INVALID_INDEX, $index);
				}

				// Get the field index and pass to the function
				$f = $this->_indexes[$index];

				// Add a boolean to the beinning of the arguments if this is a single
				array_unshift($arguments, ($matches[2][0] == $s));

				// Add the field name to the beginning of the arguments
				array_unshift($arguments, $f);

				return call_user_func_array(
					array($this, 'internal'.ucfirst($matches[1][0]).'ByIndex'), 
					$arguments
				);
			}

			// The default methods
			// ->getContent($id)
			// ->getContents()
			// ->removeContent($id)
			// ->updateContent($id)

			switch($method)
			{
				case 'get'.$p:
				{
					$internal = 'internalGetAll';
					break;
				}
				case 'get'.$s:
				{
					// Check against the specific method for access
					// for instance getPageById and getPage should have the same
					// if 'id' is the default
					$this->accessDefault('get'.$s);
					$internal = 'internalGetByIndex';
					array_unshift($arguments, true);
					array_unshift($arguments, $this->_defaultField);
					break;
				}
				case 'remove'.$s:
				{
					$this->accessDefault('remove'.$s);
					$internal = 'internalRemoveByIndex';
					array_unshift($arguments, $this->_defaultField);
					break;
				}	
				case 'update'.$s:
				{
					$this->accessDefault('update'.$s);
					$internal = 'internalUpdateByIndex';
					array_unshift($arguments, $this->_defaultField);
					break;
				}
			}

			if (!$internal)
				throw new CustomServiceError(CustomServiceError::INVALID_METHOD, $internal);

			return call_user_func_array(array($this, $internal), $arguments);
		}

		/**
		*  Get the default part of the property name
		*  @method accessDefault
		*  @private
		*  @param {String} method The name of the default method (without "ByField")
		*/
		private function accessDefault($method)
		{
			if ($this->_defaultField) 
				throw new CustomServiceError(CustomServiceError::NO_DEFAULT_INDEX);
			
			$this->access($method.'By'.ucfirst($this->_defaultField));
		}

		/**
		*  Internal method for getting result by an index
		*  @method internalGetByIndex
		*  @private
		*  @param {CustomField} index The index field to search on
		*  @param {Boolean} isSingle If the index search is a single
		*  @param {Array|mixed} search The value to search on
		*  @return {Array|Object} The collection of objects or a single object matching
		*     the className from the constuction
		*/
		private function internalGetByIndex(CustomField $index, $isSingle, $search)
		{
			$results = $this->db->select($this->_properties)
				->from($this->table)
				->where("`{$index->id}` in " . $this->valueSet($search, $index->type))
				->results();

			if (!$results) return null;

			$results = $this->bindObjects(
				$results, 
				$this->className,
				$this->_mappings
			);

			// We should only return the actual item if this is a single search
			// and the index isn't an array of items
			return !is_array($search) && $isSingle ? $results[0] : $results;
		}

		/**
		*  Internal getting a collection of items
		*  @method internalGetAll
		*  @private
		*  @return {Array} The collection of objects
		*/
		private function internalGetAll()
		{
			$results = $this->db->select($this->_properties)
				->from($this->table)
				->results();

			return $this->bindObjects(
				$results, 
				$this->className,
				$this->_mappings
			);
		}

		/**
		*  The internal remove of items by index
		*  @method internalRemoveByIndex
		*  @private
		*  @param {CustomField} index The index field to search on
		*  @param {Array|mixed} search The value to search on
		*  @return {Boolean} If the remove was successful
		*/
		private function internalRemoveByIndex(CustomField $index, $search)
		{
			return $this->db->delete($this->table)
				->where("`{$index->id}` in " . $this->valueSet($search, $index->type))
				->result();
		}

		/**
		*  The internal remove of items by index
		*  @method internalUpdateByIndex
		*  @private
		*  @param {CustomField} index The index field to search on
		*  @param {mixed} search The value to search on
		*  @param {Dictionary|String} prop The property name or map of properties to update
		*  @param {mixed} [value=null] If updating a single property, the property name
		*  @return {Boolean} If the remove was successful
		*/
		private function internalUpdateByIndex(CustomField $index, $search, $prop, $value=null)
		{
			// Validate the index search
			$this->verify($search, $index->type);

			if (!is_array($prop))
			{
				$prop = array($prop => $value);
			}
			
			$properties = array();
			foreach($prop as $k=>$p)
			{
				if (isset($this->_fieldsByName[$k]))
				{
					$f = $this->_fieldsByName[$k];
					if ($f->type !== null)
					{
						$this->verify($p, $f->type);
					}
					$properties[$k] = $p;
				}
			}

			return $this->db->update($this->table)
				->set($properties)
				->where("`{$index->id}`='$search'")
				->result();
		}
	}
}