<?php

namespace Canteen\Services
{
	use Canteen\Utilities\Validate;

	class ObjectServiceField
	{
		/** 
		*  The database field name 
		*  @property {String} id
		*/
		public $id;

		/** 
		*  The property name on the final bind object
		*  @property {String} property
		*/
		public $name;

		/** 
		*  The select property for doing a mysql select
		*  @property {String} select
		*/
		public $select;

		/** 
		*  The validation type to use for this field
		*  @property {RegExp|Array} type
		*/
		public $type;

		/**
		*  The prepend mapping for the bind, useful for prending a path to a file name
		*  @property {String} prepend
		*/
		public $prepend; 

		/**
		*  If the property can be index for a select
		*  @property {Boolean} isIndex
		*  @default false
		*/
		public $isIndex = false;

		/**
		*  The default index such as the 'id' field
		*  @property {Boolean} isDefault
		*  @default false
		*/
		public $isDefault = false;

		/**
		*  If we should sort queries by this field, null is no sort,
		*  can either be desc or asc
		*  @property {String} orderBy
		*  @default null
		*/
		public $orderBy = null;

		/**
		*  A custom field is used by the ObjectService class
		*  to represent property and db field definitions
		*  @class ObjectServiceField
		*  @constructor
		*  @param {String} id The name of the database field
		*  @param {RegExp|Array} [type=null] The validation type or set of items to match, default is no validation
		*  @param {String} [name=null] The property name
		*/
		public function __construct($id, $type=null, $name=null)
		{
			$this->id = $id;
			$this->type = $type;
			$this->name = $name === null ? self::fieldToProperty($id) : $name;
			$this->isIndex = false;

			// Specific boolean selector
			if ($type == Validate::BOOLEAN)
			{
				$this->select = 'IF(`'.$id.'` > 0, 1, null) as `'.$this->name.'`';
			}
			// The if the name is the same as id, use that!
			else if ($id == $this->name)
			{
				$this->select = '`'.$id.'`';
			}
			// Default to select field as name
			else
			{
				$this->select = '`'.$id.'` as `'.$this->name.'`';
			}

			// Add the optional prepend mapping
			$this->prepend = null;
		}

		/**
		*  Set an option
		*  @method option
		*  @private
		*  @param {String} property Either type, name, isIndex, select, prepend
		*  @param {mixed} value The value of the property to set
		*  @return {ObjectServiceField} Return the field for chaining
		*/
		private function option($property, $value)
		{
			$this->$property = $value;
			return $this;
		}

		/**
		*  Set the field to be an index, a method can be called, getItemsByName, where name
		*  is the indexed field. Database should also consider this an index.
		*  @method setIndex
		*  @param {Boolean} [isIndex=true] If the field is index
		*  @return {ObjectServiceField} Return the field for chaining
		*/
		public function setIndex($isIndex=true)
		{
			return $this->option('isIndex', $isIndex);
		}

		/**
		*  Set the default index, e.g., instead of calling getItemById, you can do getItem
		*  @method setDefault
		*  @param {Boolean} [isDefault=true] If the field is the default index
		*  @return {ObjectServiceField} Return the field for chaining
		*/
		public function setDefault($isDefault=true)
		{
			// The default should also be an index
			return $this->setIndex($isDefault)
				->option('isDefault', $isDefault);
		}

		/**
		*  Set a custom SQL select for this item
		*  @method setSelect
		*  @param {String} select The SQL select entry that returns this item by name
		*  @return {ObjectServiceField} Return the field for chaining
		*/
		public function setSelect($select)
		{
			return $this->option('select', $select);
		}

		/**
		*  Prepend this item with a string, useful for prepending full file path
		*  to a file name stored in the database.
		*  @method setPrepend
		*  @param {String} prepend The string to prepend to result value
		*  @return {ObjectServiceField} Return the field for chaining
		*/
		public function setPrepend($prepend)
		{
			return $this->option('prepend', $prepend);
		}

		/**
		*  If we should sort collection selects by this property.
		*  @method setOrderBy
		*  @param {String} [orderBy='asc'] The string to prepend to result value
		*  @return {ObjectServiceField} Return the field for chaining
		*/
		public function setOrderBy($orderBy='asc')
		{
			return $this->option('orderBy', $orderBy);
		}

		/**
		*  Represent the query as a SQL statement
		*  @method __toString
		*  @return {String} The query in SQL string form 
		*/
		public function __toString()
		{
			return $this->select;
		}

		/**
		*  Convert a mysql field (eg. content_id) to property (e.g. contentId)
		*  @method fieldToProperty 
		*  @static
		*  @param {String} id The input field name (lowercase, comma separated)
		*  @return {String} The property name (lower, camel-cased)
		*/
		public static function fieldToProperty($id)
		{
			if (preg_match('/^[a-z]+\_[a-z\_]+$/', $id))
			{
				$parts = explode('_', $id);
				
				for($i = 1; $i < count($parts); $i++)
				{
					$parts[$i] = ucfirst($parts[$i]);
				}
				return implode('', $parts);
			}
			return $id;
		}
	}
}