<?php

namespace Canteen\Services
{
	use Canteen\Utilities\Validate;

	class CustomField
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
		*  @property {String} prependMap
		*/
		public $prependMap; 

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
		*  A custom field is used by the CustomService class
		*  to represent property and db field definitions
		*  @class CustomField
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

			// The if the name is the same as id, use that!
			if ($id == $this->name)
			{
				$this->select = $id;
			}
			// Specific boolean selector
			else if ($type == Validate::BOOLEAN)
			{
				$this->select = 'IF(`'.$id.'` > 0, 1, null) as `'.$this->name.'`';
			}
			// Default to select field as name
			else
			{
				$this->select = '`'.$id.'` as `'.$this->name.'`';
			}

			// Add the optional prepend mapping
			$this->prependMap = null;
		}

		/**
		*  Set an option
		*  @method option
		*  @param {String} property Either type, name, isIndex, select, prependMap
		*  @param {mixed} value The value of the property to set
		*  @return {CustomField} Return the field for chaining
		*/
		public function option($property, $value)
		{
			$this->$property = $value;
			return $this;
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