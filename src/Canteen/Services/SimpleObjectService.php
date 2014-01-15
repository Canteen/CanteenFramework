<?php

namespace Canteen\Services
{
	use Canteen\Authorization\Privilege;
	use Canteen\Errors\ObjectServiceError;

	abstract class SimpleObjectService extends ObjectService
	{
		/** 
		*  The item definition
		*  @property {ObjectServiceItem} item
		*  @protected
		*/
		protected $item;

		/**
		*  This is like the ObjectService but provides some convience methods if only
		*  dealing with one object type. This include custom verify* methods for checking
		*  different field types (e.g. `$this->verifyName($name)`). Located in the namespace __Canteen\Services__.
		*  @class SimpleObjectService
		*  @extends ObjectService
		*  @constructor
		*  @param {String} alias The name of the service alias and the name of the table
		*  @param {String} className Class to bind database result to
		*  @param {String} table The table that the objects are on
		*  @param {Array} field The collection of ObjectServiceField objects
		*  @param {String} [itemName] The optional item name to specify, defaults to 
		*	unqalified class name (without the full namespace)
		*  @param {String} [itemsName] The optional items name (plural) of itemName 
		*/
		public function __construct($alias, $className, $table, $fields, $itemName=null, $itemsName=null)
		{
			parent::__construct($alias);

			$this->item = $this->registerItem(
				$className, 
				$table, 
				$fields, 
				$itemName, 
				$itemsName
			);
		}

		/**
		*  General magic getter to get pass-though methods
		*/
		public function __get($name)
		{
			switch($name)
			{
				/**
				*  The list of select properties
				*  @property {Array} properties
				*  @readOnly
				*/
				case 'properties':

				/**
				*  The list of prepend map
				*  @property {Dictionary} prepends
				*  @readOnly
				*/
				case 'prepends':

				/**
				*  The name of the class to bind with
				*  @property {String} className
				*  @readOnly
				*/
				case 'className':

				/**
				*  The name of the table of the custom type
				*  @property {String} table
				*  @readOnly
				*/
				case 'table':
				{
					return $this->item->$name;
				}
				/**
				*  The reference to the object
				*  @property {ObjectServiceItem} item
				*  @readOnly
				*/
				case 'item':
				{
					return $this->item;
				}				
			}
			return parent::__get($name);
		}

		/**
		*  Use the install method that comes built-in, this is experimental
		*  and may not set the best default data type on the table. It's encouraged
		*  that manual adjustments are made afterwards.
		*  @method install
		*  @protected
		*  @return {Boolean} If the install was successful
		*/
		protected function install()
		{
			$this->access(__METHOD__);
			return $this->installItem($this->item);
		}

		/**
		*  Override of the dynamic call method to call verify* methods, for instance
		*  if we're validating Name it would be $this->verifyName($value)
		*  @method __call
		*  @param {String} method The name of the method to call
		*  @param {Array} args  The collection of arguments
		*/
		public function __call($method, $args)
		{
			// Pass-throughs for the item's methods
			switch($method)
			{
				/**
				*  Register additional where clauses for the SQL select on get methods
				*  @method setWhere
				*  @protected
				*  @param {Array|String*} args The collection of extra SQL select where 
				*	 parameters to add to all get selections
				*  @return {ObjectService} The instance of this class, for chaining
				*/
				case 'setWhere' :

				/**
				*  Set additional selection properties not part of the field set
				*  @method setProperties
				*  @protected
				*  @param {Array|String*} [props=null] N-number of strings to set as additional properties,
				*	 or a collection of strings to add to the existing properties.
				*  @return {ObjectService} The instance of this class, for chaining
				*/
				case 'setProperties' :

				/**
				*  Set the collection or single prepend the field value
				*  @method setPrepends
				*  @protected
				*  @param {Dictionary|String} maps If null, returns prepends Dictionary
				*  @param {String} [value=null] The value if setting a single map
				*  @return {ObjectService} The instance of this class, for chaining
				*/
				case 'setPrepends' :
				{
					call_user_func_array(array($this->item, $method), $args);
					break;
				}
				default :
				{
					// Check for validation call with a field name some method that starts
					// verify*($value), for instance verifyItemId($id)
					if (preg_match('/^verify[A-Z][a-zA-Z0-9]*$/', $method))
					{
						// Extract the field name from the method name
						$fieldName = str_replace('verify', '', $method);
						$fieldName = strtolower(substr($fieldName, 0, 1)).substr($fieldName, 1);

						if (is_array($args) && count($args) != 1)
							throw new ObjectServiceError(ObjectServiceError::WRONG_ARG_COUNT, [$method, 1, count($args)]);

						$this->item->verify($fieldName, $args[0]);
					}
					else
					{
						// pass to the parent
						throw new ObjectServiceError(ObjectServiceError::INVALID_METHOD, $method);
					}
					break;
				}
			}
			return $this;
		}

		/**
		*  This allows for the internal dynamic calling of methods, the methods supported 
		*  include getItems, getItem, updateItem, removeItem, 
		*  getTotalItems, getTotalItem, getItemBy[IndexName], getTotalItemBy[IndexName],
		*  removeItemBy[IndexName], updateItemBy[IndexName].
		*  @method call
		*  @protected
		*  @param {mixed} [args*] The collection of arguments
		*/
		protected function call($args=null)
		{			
			return $this->callByItem($this->item, $this->getCaller(), func_get_args());
		}
	}
}