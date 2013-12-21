<?php

namespace Canteen\Services
{
	use Canteen\Authorization\Privilege;
	use Canteen\Errors\ObjectServiceError;

	class ObjectService extends Service
	{
		/**
		*  The name of the table of the custom type
		*  @property {String} table
		*  @protected
		*/
		public $table;

		/**
		*  The name of the class to bind with
		*  @property {String} className
		*  @protected
		*/
		public $className;

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
		*  The collection of ObjectServiceField objects
		*  @property {Array} _fields
		*  @private
		*/
		private $_fields;

		/**
		*  The main field, default
		*  @property {ObjectServiceField} _defaultField
		*  @private
		*/
		private $_defaultField = null;

		/**
		*  The map of ObjectServiceField objects to their names
		*  @property {Dictionary} _fieldsByName
		*  @private
		*/
		private $_fieldsByName = array();

		/**
		*  The property prepend prepends
		*  @property {Dictionary} _prepends
		*  @private
		*/
		private $_prepends = array();

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
		*  Additional get where properties
		*  @property {Array} _getWhere
		*  @private
		*  @default array()
		*/
		private $_getWhere = array();

		/**
		*  Optional property to order the database select by a property name
		*  @property {String} _getOrderBy
		*  @private
		*/
		private $_getOrderBy = null;

		/**
		*  The direction of the orderBy, if using _getOrderBy property
		*  @property {String} getOrder
		*  @private
		*  @default asc
		*/
		private $_getOrderDirection = null;

		/**
		*  The ObjectService class is an easy way to do custom
		*  data types. 
		*  @class ObjectService
		*  @constructor
		*  @param {String} alias The name of the service alias
		*  @param {String} className Class to bind database result to
		*  @param {Array} field The collection of ObjectServiceField objects
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
				$this->_fieldsByName[$f->name] = $f;
				$this->_properties[] = (string)$f;

				if ($f->prepend)
					$this->_prepends[$f->name] = $f->prepend;

				if ($f->isIndex)
					$this->_indexes[$f->name] = $f;
				
				if ($f->isDefault)
					$this->_defaultField = $f;
			}
		}

		/**
		*  Register additional where clauses for the SQL select on get methods
		*  @method where
		*  @protected
		*  @param {Array|String*} args The collection of extra SQL select where 
		*     parameters to add to all get selections
		*  @return {ObjectService} The instance of this class, for chaining
		*/
		protected function where($args)
		{
			$args = is_array($args) ? $args : func_get_args();
			$this->_getWhere = array_merge($this->_getWhere, $args);
			return $this;
		}

		/**
		*  Add an order by to the get SQL selection
		*  @method orderBy
		*  @protected
		*  @param {String} name The field name to select on
		*  @param {String} [direction='asc'] The direction of the order, either asc or desc
		*  @return {ObjectService} The instance of this class, for chaining
		*/
		protected function orderBy($name, $direction='asc')
		{
			$this->_getOrderBy = $name;
			$this->_getOrderDirection = $direction;
			return $this;
		}

		/**
		*  Convience method for the field validation wrapper for verify
		*  but call by name.
		*  @method validate
		*  @private
		*  @param {Dictionary|String} fieldName The name of the field or a map of name=>values
		*  @param {mixed} [value=null] The value to check against
		*  @return {ObjectService} The instance of this object for chaining
		*/
		private function validate($fieldName, $value=null)
		{
			if (is_array($fieldName))
			{
				foreach($fieldName as $n=>$v)
				{
					$this->validate($n, $v);
				}
			}
			else
			{
				if (!isset($this->_fieldsByName[$fieldName]))
					throw new ObjectServiceError(ObjectServiceError::INVALID_FIELD_NAME, $fieldName);

				// Do the validation
				$type = $this->_fieldsByName[$fieldName]->type;
				if ($type) $this->verify($value, $type);
			}
			return $this;
		}

		/**
		*  Conviencence method to inserting a new row into a table, this does
		*  all the field validation and insert.
		*  @method add
		*  @protected
		*  @param {Dictionary} properties The collection map of field names to values
		*  @return {int} The result
		*/
		protected function add($properties)
		{
			if (!$this->_defaultField) 
				throw new ObjectServiceError(ObjectServiceError::NO_DEFAULT_INDEX);
			
			// Check the access on the calling method
			$this->access($this->getCaller());

			$this->validate($properties);

			// Get the next field ID
			$values = array();

			// Convert the named properties into field inserts
			foreach($properties as $name=>$value)
			{
				$field = $this->_fieldsByName[$name];
				$values[$field->id] = $value;
			}

			// If the default index isn't included,
			// we'll use the next Id on the table, this is only
			// for index things
			if (!isset($values[$this->_defaultField->name]))
			{
				$values[$this->_defaultField->id] = $this->db->nextId(
					$this->table, 
					$this->_defaultField->id
				);
			}

			// Insert the item
			return $this->db->insert($this->table)
				->values($values)
				->result() ? $values[$this->_defaultField->id] : false;
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
		*  Get or set the collection of prepend prepends
		*  @method prepends
		*  @protected
		*  @param {Dictionary|String} [maps=null] If null, returns prepends Dictionary
		*  @param {String} [value=null] The value if setting a single map
		*  @return {Dictionary} The prepends
		*/
		protected function prepends($maps=null, $value=null)
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
				$this->_prepends = array_merge($this->_prepends, $maps);
			}
			return $this->_prepends;
		}
		
		/**
		*  Convenience function for creating a new field
		*  @method field
		*  @protected
		*  @param {String} id The name of the field on the database
		*  @param {RegExp|Array} [type=null] The validation type or set of values, default is no validation
		*  @param {String} [name=null] The name of the php property
		*  @return {ObjectServiceField} The new custom field object
		*/
		protected function field($id, $type=null, $name=null)
		{
			return new ObjectServiceField($id, $type, $name);
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
		*  Override of the dynamic call method to call validate* methods, for instance
		*  if we're validating Name it would be $this->validateName($value)
		*  @method __call
		*  @param {String} method The name of the method to call
		*  @param {Array} args  The collection of arguments
		*/
		public function __call($method, $args)
		{
			// Check for validation call
			if (preg_match('/^validate([A-Z][a-zA-Z0-9]*)$/', $method))
			{
				$name = str_replace('validate', '', $method);
				$name = strtolower(substr($name, 0, 1)).substr($name, 1);

				if (!isset($this->_fieldsByName[$name]))
					throw new ObjectServiceError(ObjectServiceError::INVALID_FIELD_NAME, $name);

				if (is_array($args) && count($args) != 1)
					throw new ObjectServiceError(ObjectServiceError::WRONG_ARG_COUNT, array($method, 1, count($args)));

				$this->validate($name, $args[0]);
				return;
			}
		}

		/**
		*  This allows for the internal dynamic calling of methods, the methods supported 
		*  include getItems, getItem, updateItem, removeItem, 
		*  getTotalItems, getTotalItem, getItemBy[IndexName], getTotalItemBy[IndexName],
		*  removeItemBy[IndexName], updateItemBy[IndexName].
		*  @method call
		*  @protected
		*  @param {mixed} [arguments*] The collection of arguments
		*/
		protected function call($args=null)
		{
			$method = $this->getCaller();
			$args = func_get_args();
			$internal = null;

			// Check for access control of the method
			$this->access($method);

			$s = $this->singleItemName;
			$p = $this->pluralItemName;

			// Check for function calls where By is called
			if (preg_match_all('/^(get|update|remove|getTotal)('.$s.'|'.$p.')By([A-Z][A-Za-z]+)$/', $method, $matches))
			{
				$index = $matches[3][0];

				// Lower case the first letter to compare the field name
				$index = strtolower(substr($index, 0, 1)).substr($index, 1);

				if (!isset($this->_indexes[$index]))
				{
					throw new ObjectServiceError(ObjectServiceError::INVALID_INDEX, $index);
				}

				// Get the field index and pass to the function
				$f = $this->_indexes[$index];

				// Add a boolean to the beinning of the arguments if this is a single
				array_unshift($args, ($matches[2][0] == $s));

				// Add the field name to the beginning of the arguments
				array_unshift($args, $f);

				return call_user_func_array(
					array($this, 'internal'.ucfirst($matches[1][0]).'ByIndex'), 
					$args
				);
			}

			// The default methods
			switch($method)
			{
				// getItems
				case 'get'.$p:
				{
					$internal = 'internalGetAll';
					break;
				}
				// getTotalItems
				case 'getTotal'.$p:
				{
					$internal = 'internalGetTotalAll';
					break;
				}
				// getTotalItem
				case 'getTotal'.$s:
				{
					$this->accessDefault('getTotal'.$s);
					$internal = 'internalGetTotalByIndex';
					array_unshift($args, true);
					array_unshift($args, $this->_defaultField);
					break;
				}
				// getItem
				case 'get'.$s:
				{
					$this->accessDefault('get'.$s);
					$internal = 'internalGetByIndex';
					array_unshift($args, true);
					array_unshift($args, $this->_defaultField);
					break;
				}
				// removeItem
				case 'remove'.$s:
				{
					$this->accessDefault('remove'.$s);
					$internal = 'internalRemoveByIndex';
					array_unshift($args, $this->_defaultField);
					break;
				}	
				// updateItem
				case 'update'.$s:
				{
					$this->accessDefault('update'.$s);
					$internal = 'internalUpdateByIndex';
					array_unshift($args, $this->_defaultField);
					break;
				}
			}

			if (!$internal)
				throw new ObjectServiceError(ObjectServiceError::INVALID_METHOD, $internal);

			return call_user_func_array(array($this, $internal), $args);
		}

		/**
		*  Get the default part of the property name
		*  @method accessDefault
		*  @private
		*  @param {String} method The name of the default method (without "ByField")
		*/
		private function accessDefault($method)
		{
			if (!$this->_defaultField) 
				throw new ObjectServiceError(ObjectServiceError::NO_DEFAULT_INDEX);
			
			$this->access($method.'By'.ucfirst($this->_defaultField->name));
		}

		/**
		*  Internal method for getting result by an index
		*  @method internalGetByIndex
		*  @private
		*  @param {ObjectServiceField} index The index field to search on
		*  @param {Boolean} isSingle If the index search is a single
		*  @param {Array|mixed} search The value to search on
		*  @param {int} [lengthOrIndex=null] The starting index or elements to return
		*  @param {int} [duration=null] The duration of the items
		*  @return {Array|Object} The collection of objects or a single object matching
		*     the className from the constuction
		*/
		private function internalGetByIndex(ObjectServiceField $index, $isSingle, $search, $lengthOrIndex=null, $duration=null)
		{
			$query = $this->db->select($this->_properties)
				->from($this->table)
				->where("`{$index->id}` in " . $this->valueSet($search, $index->type));

			if ($this->_getOrderBy !== null)
			{
				$query->orderBy($this->_getOrderBy, $this->_getOrderDirection);
			}
				
			if (count($this->_getWhere))
			{
				$query->where($this->_getWhere);
			}

			if ($lengthOrIndex !== null)
			{
				$this->verify($lengthOrIndex);
				if ($duration !== null) $this->verify($duration);
				$query->limit($lengthOrIndex, $duration);
			}

			$results = $query->results();

			if (!$results) return null;

			$results = $this->bindObjects(
				$results, 
				$this->className,
				$this->_prepends
			);

			// We should only return the actual item if this is a single search
			// and the index isn't an array of items
			return !is_array($search) && $isSingle ? $results[0] : $results;
		}

		/**
		*  Internal method for getting result by an index
		*  @method internalGetTotalByIndex
		*  @private
		*  @param {ObjectServiceField} index The index field to search on
		*  @param {Boolean} isSingle If the index search is a single
		*  @param {Array|mixed} search The value to search on
		*  @return {Array|Object} The collection of objects or a single object matching
		*     the className from the constuction
		*/
		private function internalGetTotalByIndex(ObjectServiceField $index, $isSingle, $search)
		{
			$query = $this->db->select($this->_properties)
				->from($this->table)
				->where("`{$index->id}` in " . $this->valueSet($search, $index->type));
				
			if (count($this->_getWhere))
			{
				$query->where($this->_getWhere);
			}

			return $query->length();
		}

		/**
		*  Internal getting a collection of items
		*  @method internalGetAll
		*  @private
		*  @param {int} [lengthOrIndex=null] The starting index or elements to return
		*  @param {int} [duration=null] The duration of the items
		*  @return {Array} The collection of objects
		*/
		private function internalGetAll($lengthOrIndex=null, $duration=null)
		{
			$query = $this->db->select($this->_properties)
				->from($this->table);
		
			if ($this->_getOrderBy !== null)
			{
				$query->orderBy($this->_getOrderBy, $this->_getOrderDirection);
			}
				
			if (count($this->_getWhere))
			{
				$query->where($this->_getWhere);
			}

			if ($lengthOrIndex !== null)
			{
				$this->verify($lengthOrIndex);
				if ($duration !== null) $this->verify($duration);
				$query->limit($lengthOrIndex, $duration);
			}

			$results = $query->results();

			return $this->bindObjects(
				$results, 
				$this->className,
				$this->_prepends
			);
		}

		/**
		*  Internal getting a total number of items
		*  @method internalGetTotalAll
		*  @private
		*  @return {int} The number of items in selection
		*/
		private function internalGetTotalAll()
		{
			$query = $this->db->select($this->_properties)
				->from($this->table);
				
			if (count($this->_getWhere))
			{
				$query->where($this->_getWhere);
			}

			return $query->length();
		}

		/**
		*  The internal remove of items by index
		*  @method internalRemoveByIndex
		*  @private
		*  @param {ObjectServiceField} index The index field to search on
		*  @param {Array|mixed} search The value to search on
		*  @return {Boolean} If the remove was successful
		*/
		private function internalRemoveByIndex(ObjectServiceField $index, $search)
		{
			return $this->db->delete($this->table)
				->where("`{$index->id}` in " . $this->valueSet($search, $index->type))
				->result();
		}

		/**
		*  The internal remove of items by index
		*  @method internalUpdateByIndex
		*  @private
		*  @param {ObjectServiceField} index The index field to search on
		*  @param {mixed} search The value to search on
		*  @param {Dictionary|String} prop The property name or map of properties to update
		*  @param {mixed} [value=null] If updating a single property, the property name
		*  @return {Boolean} If the remove was successful
		*/
		private function internalUpdateByIndex(ObjectServiceField $index, $search, $prop, $value=null)
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