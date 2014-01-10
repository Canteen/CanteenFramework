<?php

namespace Canteen\Services
{
	use Canteen\Errors\ObjectServiceError;
	
	abstract class ObjectService extends Service
	{
		/** 
		*  The dictionary of item names to ObjectServiceItem objects
		*  @property {Dictionary} items 
		*/
		public $items = array();

		/**
		*  Service provides basic methods for getting, updating, remove multiple objects 
		*  on multiple tables.
		*  @class ObjectService
		*  @extends Service
		*  @param {String} alias The name to get by reference within the Canteen Site
		*/
		public function __construct($alias)
		{
			parent::__construct($alias);
		}

		/**
		*  Add a new Item definition to the service. This is required before using
		*  any of the methods for updating, getting, removing. Located in the namespace __Canteen\Services__.
		*  @method registerItem
		*  @protected
		*  @param {String} className Class to bind database result to
		*  @param {String} table The name of the database table
		*  @param {Array} field The collection of ObjectServiceField objects
		*  @param {String} itemName The name of the item
		*  @param {String} itemsName The name of the plural items
		*  @return {ObjectServiceItem} The new service definition created
		*/
		protected function registerItem($className, $table, $fields, $itemName = null, $itemsName = null)
		{
			$item = new ObjectServiceItem(
				$className,
				$table, 
				$fields, 
				$itemName, 
				$itemsName
			);
			$this->items[$item->itemName] = $item;
			return $item;
		}

		/**
		*  The more manual method for verifying a field value.
		*  @method verifyField
		*  @protected
		*  @param {ObjectServiceItem} item Registered item
		*  @param {Dictionary|String} name The name of the field or a map of name=>values
		*  @param {mixed} [value=null] The value to check against
		*  @return {ObjectService} The instance of this object for chaining
		*/
		protected function verifyField(ObjectServiceItem $item, $name, $value=null)
		{
			$item->verify($name, $value);
			return $this;
		}

		/**
		*  Auto install the service item
		*  @method installItem
		*  @protected
		*  @param {ObjectServiceItem} item Registered item
		*  @return {Boolean} If item was installed successfully
		*/
		protected function installItem(ObjectServiceItem $item)
		{
			return (Boolean) $this->db->execute($item->getInstallQuery());
		}

		/**
		*  Auto install multiple service items
		*  @method installItems
		*  @protected
		*  @param {Array|ObjectServiceItem} items Registered items as different arguments
		*	  or as a collection of ObjectServiceItems
		*  @return {Boolean} If item was installed successfully
		*/
		protected function installItems($items)
		{
			$success = true;
			$items = is_array($items) ? $items : func_get_args();
			foreach($items as $item)
			{
				if (!$this->installItem($item))
				{
					$success = false;
				}
			}
			return $success;
		}

		/**
		*  Override of the dynamic call method to call install* methods, for instance
		*  if we're install an item name Object it would be `$this->installObject()`
		*  @method __call
		*  @param {String} method The name of the method to call
		*  @param {Array} args  The collection of arguments
		*  @return {ObjectService} The instance of ObjectService for chaining
		*/
		public function __call($method, $args)
		{
			// Auto-detect the name of the item, needs to be called within
			// a method that has the name of an item
			$item = $this->autoDetectItem($this->getCaller());

			if (preg_match('/^install'.$item->itemName.'$/', $method))
			{
				return $this->installItem($item);
			}
			else
			{
				throw new ObjectServiceError(ObjectServiceError::INVALID_METHOD, $method);
			}
			return $this;
		}

		/**
		*  Conviencence method to inserting a new row into a table, this does
		*  all the field validation and insert.
		*  @method addByItem
		*  @protected
		*  @param {ObjectServiceItem} item The item to use
		*  @param {Dictionary} properties The collection map of field names to values
		*  @return {int} The result
		*/
		protected function addByItem(ObjectServiceItem $item, array $properties)
		{
			// Check the access on the calling method
			$this->access($this->getCaller());

			// Validate the properties
			$item->verify($properties);

			// Get the next field ID
			$values = array();

			// Convert the named properties into field inserts
			foreach($properties as $name=>$value)
			{
				// Check for invalid field name
				if (!isset($item->fieldsByName[$name])) 
					throw new ObjectServiceError(ObjectServiceError::INVALID_FIELD_NAME, $name);

				$field = $item->fieldsByName[$name];
				$values[$field->id] = $value;
			}

			// If the default index isn't included,
			// we'll use the next Id on the table, this is only
			// for index things
			if ($item->defaultField && !isset($values[$item->defaultField->id]))
			{
				$values[$item->defaultField->id] = $this->db->nextId(
					$item->table, 
					$item->defaultField->id
				);
			}

			// Insert the item
			$success = $this->db->insert($item->table)
				->values($values)
				->result();

			// If there's a default field, we'll return the id
			// of the next item
			if ($item->defaultField)
				return $success ? $values[$item->defaultField->id] : false;
			else
				return $success;
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
		*  This allows for the internal dynamic calling of methods, the methods supported 
		*  include getItems, getItem, updateItem, removeItem, 
		*  getTotalItems, getTotalItem, getItemBy[IndexName], getTotalItemBy[IndexName],
		*  removeItemBy[IndexName], updateItemBy[IndexName].
		*  @method call
		*  @protected
		*  @param {mixed} [args*] Additional arguments to call
		*  @return {mixed} The result of the method call
		*/
		protected function call($args=null)
		{
			$method = $this->getCaller();
			$item = $this->autoDetectItem($method);
			return $this->callByItem($item, $method, func_get_args());
		}

		/**
		*  Auto detect the item name based on the name of the method that called it
		*  @method autoDetectItem
		*  @private
		*  @param {String} method The name of the method
		*  @return {ObjectServiceItem} Returns the item or throw error
		*/
		private function autoDetectItem($method)
		{
			// Default the item name to use to be false
			$itemName = false;

			foreach($this->items as $name=>$item)
			{
				// Compare against the single and plural name
				if (preg_match('/'.$name.'|'.$item->itemsName.'/', $method))
				{
					$itemName = $name;
					break;
				}
			}

			if (!$itemName)
				throw new ObjectServiceError(ObjectServiceError::UNREGISTERED_ITEM, $method);

			if (!isset($this->items[$itemName]))
				throw new ObjectServiceError(ObjectServiceError::UNREGISTERED_ITEM, $method);

			return $this->items[$itemName];
		}

		/**
		*  Generally called for calling the item methods
		*  @method callByItem
		*  @protected
		*  @param {ObjectServiceItem} item The item to call for
		*  @param {String} method The method name to call
		*  @param {Array} args The collection of additional arguments
		*  @return {mixed} The result of the method call
		*/
		protected function callByItem(ObjectServiceItem $item, $method, array $args)
		{
			// See if we can access this method
			$this->access($method);

			// The the single and plural name
			$s = $item->itemName;
			$p = $item->itemsName;

			// Check for function calls where By is called
			if (preg_match_all('/^(get|update|remove|getTotal)('.$s.'|'.$p.')By([A-Z][A-Za-z]+)$/', $method, $matches))
			{
				$index = $matches[3][0];

				// Lower case the first letter to compare the field name
				$index = strtolower(substr($index, 0, 1)).substr($index, 1);

				if (!isset($item->indexes[$index]))
					throw new ObjectServiceError(ObjectServiceError::INVALID_INDEX, $index);

				// Get the field index and pass to the function
				$f = $item->indexes[$index];

				// Add a boolean to the beinning of the arguments if this is a single
				if ($matches[1][0] == 'get')
				{
					array_unshift($args, ($matches[2][0] == $s));
				}

				// Add the field name to the beginning of the arguments
				array_unshift($args, $f);

				// Add the item definition
				array_unshift($args, $item);

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
					$this->accessDefault($item, 'getTotal'.$s);
					$internal = 'internalGetTotalByIndex';
					array_unshift($args, true);
					array_unshift($args, $item->defaultField);
					break;
				}
				// getItem
				case 'get'.$s:
				{
					$this->accessDefault($item, 'get'.$s);
					$internal = 'internalGetByIndex';
					array_unshift($args, true);
					array_unshift($args, $item->defaultField);
					break;
				}
				// removeItem
				case 'remove'.$s:
				{
					$this->accessDefault($item, 'remove'.$s);
					$internal = 'internalRemoveByIndex';
					array_unshift($args, $item->defaultField);
					break;
				}	
				// updateItem
				case 'update'.$s:
				{
					$this->accessDefault($item, 'update'.$s);
					$internal = 'internalUpdateByIndex';
					array_unshift($args, $item->defaultField);
					break;
				}
			}

			if (!$internal)
				throw new ObjectServiceError(ObjectServiceError::INVALID_METHOD, $internal);

			array_unshift($args, $item);

			return call_user_func_array(array($this, $internal), $args);
		}

		/**
		*  Get the default part of the property name
		*  @method accessDefault
		*  @private
		*  @param {String} method The name of the default method (without "ByField")
		*/
		private function accessDefault($item, $method)
		{
			if (!$item->defaultField) 
				throw new ObjectServiceError(ObjectServiceError::NO_DEFAULT_INDEX);
			
			$this->access($method.'By'.ucfirst($item->defaultField->name));
		}

		/**
		*  Internal method for getting result by an index
		*  @method internalGetByIndex
		*  @private
		*  @param {ObjectServiceItem} item The item definition
		*  @param {ObjectServiceField} index The index field to search on
		*  @param {Boolean} isSingle If the index search is a single
		*  @param {Array|mixed} search The value to search on
		*  @param {int} [lengthOrIndex=null] The starting index or elements to return
		*  @param {int} [duration=null] The duration of the items
		*  @return {Array|Object} The collection of objects or a single object matching
		*	 the className from the constuction
		*/
		private function internalGetByIndex(ObjectServiceItem $item, ObjectServiceField $index, $isSingle, $search, $lengthOrIndex=null, $duration=null)
		{
			$query = $this->db->select($item->properties)
				->from($item->table)
				->where("`{$index->id}` in " . $this->valueSet($search, $index->type));

			$item->orderByQuery($query);
				
			if (count($item->where))
			{
				$query->where($item->where);
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
				$item->className,
				$item->prepends
			);

			// We should only return the actual item if this is a single search
			// and the index isn't an array of items
			return !is_array($search) && $isSingle ? $results[0] : $results;
		}

		/**
		*  Internal method for getting result by an index
		*  @method internalGetTotalByIndex
		*  @private
		*  @param {ObjectServiceItem} item The item definition
		*  @param {ObjectServiceField} index The index field to search on
		*  @param {Boolean} isSingle If the index search is a single
		*  @param {Array|mixed} search The value to search on
		*  @return {Array|Object} The collection of objects or a single object matching
		*	 the className from the constuction
		*/
		private function internalGetTotalByIndex(ObjectServiceItem $item, ObjectServiceField $index, $isSingle, $search)
		{
			$query = $this->db->select('*')
				->from($this->table)
				->where("`{$index->id}` in " . $this->valueSet($search, $index->type));
				
			if (count($item->where))
			{
				$query->where($item->where);
			}

			return $query->length();
		}

		/**
		*  Internal getting a collection of items
		*  @method internalGetAll
		*  @private
		*  @param {ObjectServiceItem} item The item definition
		*  @param {int} [lengthOrIndex=null] The starting index or elements to return
		*  @param {int} [duration=null] The duration of the items
		*  @return {Array} The collection of objects
		*/
		private function internalGetAll(ObjectServiceItem $item, $lengthOrIndex=null, $duration=null)
		{
			$query = $this->db->select($item->properties)
				->from($item->table);
		
			$item->orderByQuery($query);
				
			if (count($item->where))
			{
				$query->where($item->where);
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
				$item->className, 
				$item->prepends
			);
		}

		/**
		*  Internal getting a total number of items
		*  @method internalGetTotalAll
		*  @private
		*  @param {ObjectServiceItem} item The item definition
		*  @return {int} The number of items in selection
		*/
		private function internalGetTotalAll(ObjectServiceItem $item)
		{
			$query = $this->db->select('*')
				->from($this->table);
				
			if (count($item->where))
			{
				$query->where($item->where);
			}

			return $query->length();
		}

		/**
		*  The internal remove of items by index
		*  @method internalRemoveByIndex
		*  @private
		*  @param {ObjectServiceItem} item The item definition
		*  @param {ObjectServiceField} index The index field to search on
		*  @param {Array|mixed} search The value to search on
		*  @return {Boolean} If the remove was successful
		*/
		private function internalRemoveByIndex(ObjectServiceItem $item, ObjectServiceField $index, $search)
		{
			return $this->db->delete($item->table)
				->where("`{$index->id}` in " . $this->valueSet($search, $index->type))
				->result();
		}

		/**
		*  The internal remove of items by index
		*  @method internalUpdateByIndex
		*  @private
		*  @param {ObjectServiceItem} item The item definition
		*  @param {ObjectServiceField} index The index field to search on
		*  @param {mixed} search The value to search on
		*  @param {Dictionary|String} prop The property name or map of properties to update
		*  @param {mixed} [value=null] If updating a single property, the property name
		*  @return {Boolean} If the remove was successful
		*/
		private function internalUpdateByIndex(ObjectServiceItem $item, ObjectServiceField $index, $search, $prop, $value=null)
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
				if (isset($item->fieldsByName[$k]))
				{
					$f = $item->fieldsByName[$k];
					if ($f->type !== null)
					{
						$this->verify($p, $f->type);
					}
					$properties[$f->id] = $p;
				}
			}

			return $this->db->update($this->table)
				->set($properties)
				->where("`{$index->id}`='$search'")
				->result();
		}
	}
}