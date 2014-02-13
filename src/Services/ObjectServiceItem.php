<?php

namespace Canteen\Services
{
	use Canteen\Utilities\Validate;
	use Canteen\Database\SelectQuery;
	use Canteen\Errors\ObjectServiceError;
	use Canteen\Database\CreateQuery;

	class ObjectServiceItem
	{
		/**
		*  The name of the table of the custom type
		*  @property {String} table
		*  @readOnly
		*/
		public $table;

		/**
		*  The name of the class to bind with
		*  @property {String} className
		*  @readOnly
		*/
		public $className;

		/**
		*  The collection of ObjectServiceField objects
		*  @property {Array} _fields
		*  @readOnly
		*/
		public $fields;

		/**
		*  The name of the single item to use for dynamic method calls
		*  @property {String} itemName
		*  @readOnly
		*/
		public $itemName;

		/**
		*  The name of multiple items to use for dynamic class
		*  @property {String} itemsName
		*  @readOnly
		*/
		public $itemsName;

		/**
		*  The main field, default
		*  @property {ObjectServiceField} defaultField
		*  @readOnly
		*/
		public $defaultField = null;

		/**
		*  The map of ObjectServiceField objects to their names
		*  @property {Dictionary} fieldsByName
		*  @readOnly
		*/
		public $fieldsByName = [];

		/**
		*  The property prepend prepends
		*  @property {Dictionary} prepends
		*/
		public $prepends = [];

		/**
		*  The collection of mysql select properties
		*  @property {Array} properties
		*  @readOnly
		*/
		public $properties = [];

		/**
		*  The map of field names that can be indexed
		*  @property {Dictionary} indexes
		*  @readOnly
		*/
		public $indexes = [];

		/**
		*  Additional get where properties
		*  @property {Array} where
		*  @readOnly
		*/
		public $where = [];

		/**
		*  The reference to the service this originates on
		*  @property {ObjectService} service
		*  @readOnly
		*/
		public $service = null;

		/**
		*  The encapsulation of a single data type, requires a predefined
		*  data class and a table name and field definitions. 
		*  @class ObjectServiceItem
		*  @constructor
		*  @param {String} className Class to bind database result to
		*  @param {String} table The name of the database table
		*  @param {Array} field The collection of ObjectServiceField objects
		*  @param {String} itemName The name of the item
		*  @param {String} itemsName The name of the plural items
		*/
		public function __construct($className, $table, $fields, $itemName = null, $itemsName = null)
		{
			$this->className = $className;
			$this->table = $table;
			$this->fields = $fields;
			$this->itemName = $itemName ? $itemName : $this->defaultSingleName();
			$this->itemsName = $itemsName ? $itemsName : $this->itemName . 's';

			foreach($fields as $f)
			{
				$this->fieldsByName[$f->name] = $f;
				$this->properties[] = (string)$f;

				if ($f->prepend)
					$this->prepends[$f->name] = $f->prepend;

				if ($f->isIndex)
					$this->indexes[$f->name] = $f;
				
				if ($f->isDefault)
					$this->defaultField = $f;
			}
		}

		/**
		*  Register additional where clauses for the SQL select on get methods
		*  @method setWhere
		*  @param {Array|String*} args The collection of extra SQL select where 
		*	 parameters to add to all get selections
		*  @return {ObjectService} The instance of this class, for chaining
		*/
		public function setWhere($args)
		{
			$args = is_array($args) ? $args : func_get_args();
			$this->where = array_merge($this->where, $args);
			return $this;
		}

		/**
		*  Add order by fields to the select query
		*  @method orderByQuery
		*  @param {SelectQuery} query The select database query
		*  @return {SelectQuery} The query object
		*/
		public function orderByQuery(SelectQuery &$query)
		{
			foreach($this->fields as $field)
			{
				if ($field->orderBy !== null)
				{
					$query->orderBy($field->id, $field->orderBy);
				}
			}
			return $query;
		}

		/**
		*  Get the select properties
		*  @method setProperties
		*  @param {Array|String*} [props] N-number of strings to set as additional properties,
		*	 or a collection of strings to add to the existing properties.
		*/
		public function setProperties($props)
		{
			$props = is_array($props) ? $props : func_get_args();
			$this->properties = array_merge($this->properties, $props);
		}

		/**
		*  Get or set the collection of prepend prepends
		*  @method setPrepends
		*  @param {Dictionary|String} maps The map of prepends by field name or string of field name
		*  @param {String} [value=null] The value if setting a single map
		*  @return {Dictionary} The prepends
		*/
		public function setPrepends($maps, $value=null)
		{
			if (is_string($maps))
			{
				$maps = [$maps => $value];
			}
			$this->prepends = array_merge($this->prepends, $maps);
		}

		/**
		*  A simple orderBy method to set the order of query
		*  @method setOrderBy
		*  @param {String} fieldName The valid name of a field on this item
		*  @param {String} [direction='asc'] The order by direction
		*  @return {ObjectService} The instance of this class, for chaining
		*/
		public function setOrderBy($fieldName, $direction='asc')
		{
			if (!isset($this->fieldsByName[$fieldName]))
				throw new ObjectServiceError(ObjectServiceError::INVALID_FIELD_NAME, $fieldName);

			foreach($this->fields as $field)
			{
				// Set the order by on the field
				if ($field->name == $fieldName)
				{
					$field->setOrderBy($direction);
				}
				// Clear all other orders
				else
				{
					$field->setOrderBy(null);
				}
			}
			return $this;
		}

		/**
		*  Convience method for the field validation wrapper for verify
		*  but call by name.
		*  @method verify
		*  @public
		*  @param {Dictionary|String} fieldName The name of the field or a map of name=>values
		*  @param {mixed} [value=null] The value to check against
		*/
		public function verify($fieldName, $value=null)
		{
			if (is_array($fieldName))
			{
				foreach($fieldName as $n=>$v)
				{
					$this->verify($n, $v);
				}
			}
			else
			{
				if (!isset($this->fieldsByName[$fieldName]))
					throw new ObjectServiceError(ObjectServiceError::INVALID_FIELD_NAME, $fieldName);

				// Do the validation
				$type = $this->fieldsByName[$fieldName]->type;

				if ($type) Validate::verify($value, $type);
			}
		}

		/**
		*  Experimental install feature to take the fields
		*  and convert them into a MySQL create table query.
		*  @method install
		*  @param {CreateQuery} query The query to install with
		*  @return {String} The sql query to install the table
		*/
		public function install(CreateQuery $query)
		{
			foreach($this->fields as $f)
			{
				$isNull = false;
				$default = null;

				if (is_array($f->type))
				{
					$query->set($f->id, $f->type, $isNull, $default);
				}
				else
				{
					$properties = '';
					switch($f->type)
					{
						case Validate::NUMERIC :
							$properties = 'int(10) unsigned';
							break;
						case Validate::BOOLEAN :
							$properties = 'tinyint(1) unsigned';
							$default = 0;
							break;
						case Validate::MYSQL_DATE :
							$properties = 'datetime';
							break;
						case null :
							$properties = 'text';
							$isNull = true;
							break;
						default :
						case Validate::FULL_TEXT :
							$properties = 'varchar(255)';
							$isNull = true;
							break;
					}
					if ($properties)
					{
						$query->field($f->id, $properties, $isNull, $default, $f->isDefault);
					}
				}
				if ($f->isIndex)
				{
					$query->key($f->id);
				}
			}
			return $query->result();
		}

		/**
		*  Get the default single name
		*  @method defaultSingleName
		*  @private
		*  @return {String} The name of a single item of this service
		*/
		private function defaultSingleName()
		{
			$class = explode('\\', $this->className);
			return $class[count($class) - 1];
		}
	}
}