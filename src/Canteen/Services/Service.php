<?php

/**
*  @module Canteen\Services
*/
namespace Canteen\Services
{	
	use Canteen\Errors\CanteenError;
	use Canteen\Errors\UserError;
	use Canteen\Authorization\Privilege;
	use Canteen\Utilities\Validate;
	use Canteen\Utilities\CanteenBase;
	use Canteen\Site;
	
	/**
	*  All services should extends this base service class. Located in the namespace __Canteen\Services__.
	*  
	*  @class Service
	*  @extends CanteenBase
	*  @constructor
	*  @param {String} alias The alias to register service as
	*/
	class Service extends CanteenBase
	{		
		/** 
		*  The registered list of services 
		*  @property {Array} registerd
		*  @private
		*  @static
		*/
		private static $registered = array();
		
		/** 
		*  A map of the class name to registered alias 
		*  @property {Array} registeredMaps
		*  @private
		*  @static
		*/
		private static $registeredMaps = array();
		
		/** 
		*  A map of the class name to registered alias to class name 
		*  @property {Array} registeredAliases
		*  @private
		*  @static
		*/
		private static $registeredAliases = array();
		
		/**
		*  The mappings used to prepend the data object paths
		*  @property {Array} mappings
		*  @private
		*/
		private $mappings = array();
		
		/**
		*  Create the service
		*/
		public function __construct($alias)
		{
			if (isset(self::$registered[$alias]))
			{
				warning("The service ('$alias') already exists, overwriting.");
			}
			self::$registered[$alias] = $this;
			self::$registeredMaps[get_class($this)] = $this;
			self::$registeredAliases[$alias] = get_class($this);
		}
		
		/**
		*  Get a particular service by alias
		*  @method get
		*  @static
		*  @param {String} aliasOrClassName The registered alias or class name
		*  @return {Service} The instance of the service
		*/
		public static function get($aliasOrClassName)
		{			
			$aliases = self::$registeredAliases;
			
			// Check if the alias is registered
			if (isset(self::$registered[$aliasOrClassName]))
			{
				return self::$registered[$aliasOrClassName];
			}
			// Check if the class is registered
			else if (isset(self::$registeredMaps[$aliasOrClassName]))
			{
				return self::$registeredMaps[$aliasOrClassName];
			}
			// See if the alias is valid
			else if (isset($aliases[$aliasOrClassName]))
			{
				return new $aliases[$aliasOrClassName];
			}
			else if (in_array($aliasOrClassName, $aliases))
			{
				return new $aliasOrClassName;
			}
			else
			{
				throw new CanteenError(CanteenError::INVALID_SERVICE_ALIAS, $aliasOrClassName);
			}
		}
		
		/**
		*  Get all the service aliases
		*  @method getAliases
		*  @static
		*  @return {Array} The aliases to className map
		*/
		public static function getAliases()
		{
			return self::$registeredAliases;
		}
		
		/**
		*  Add a single service alias
		*  @method addAlias
		*  @static
		*  @param {String} alias The service alias
		*  @param {String} className The full namespace and package class name
		*/
		public static function addService($alias, $className)
		{
			if (isset(self::$registeredAliases[$alias]))
			{
				throw new CanteenError(CanteenError::TAKEN_SERVICE_ALIAS, $alias);
			}
			self::$registeredAliases[$alias] = $className;
		}
		
		/**
		*  Convenience check to see if the user has the privilege 
		*  to do a particular action.
		*  @method privilege
		*  @protected
		*  @param {int} [required=0] The privilege required, (default is guest privilege)
		*/
		protected function privilege($required=Privilege::GUEST)
		{
			if (LOCAL) return;
			
			if (!LOGGED_IN)
			{
				throw new UserError(UserError::LOGGIN_REQUIRED);
			}
			else if (USER_PRIVILEGE < $required)
			{
				throw new UserError(UserError::INSUFFICIENT_PRIVILEGE);
			} 
		}
		
		/**
		*  Explicitly define that only certain classes can call a method.
		*  @method internal
		*  @protected
		*  @param {String} classes* The single class or array of classes or list of classes as separate arg
		*/
		protected function internal($classes)
		{
			if (LOCAL) return;
			
			$trace = debug_backtrace();
			
			// 0 is Service class, 1 is the Service class, 2 is the caller
			$class = ifsetor($trace[2]['class']); 
			
			$classes = is_array($classes) ? $classes : func_get_args();
						
			if (!$class || !in_array($class, $classes))
			{
				throw new CanteenError(CanteenError::INTERNAL_ONLY);
			}
		}
		
		/**
		*   The public getter 
		*/		
		public function __get($name)
		{
			/**
			*  Convenience getter for the database connection 
			*  @property {Database} db
			*  @readOnly
			*/
			if ($name == 'db')
			{
				return $this->site->$name;
			}
			return parent::__get($name);
		}
		
		/**
		*  Sanitize input data using the validation types above
		*  @method verify
		*  @protected
		*  @param {String} data The data to be validated, can be an array of items
		*  @param {RegExp} [type=null] The type of validation, defaults to Numeric. Can also be an array set of items
		*  @param {Boolean} [suppressErrors=false] If we should suppress throwing errors
		*  @return {mixed} If we don't verify and suppress errors, returns false, else returns the data
		*/
		protected function verify($data, $type=null, $suppressErrors=false)
		{
			return Validate::verify($data, $type, $suppressErrors);
		}
		
		/**
		*  Get a set of ids
		*  @method valueSet
		*  @protected
		*  @param {Array|String} A single id or a collection of IDs
		*  @param {RegExp} [validationType=null] See Validation class for more information on types
		*  @return {String} The MySQL formatted set of IDs
		*/
		protected function valueSet($id, $validationType=null)
		{
			$id = !is_array($id) ? array($id) : $id;
			$this->verify($id, $validationType);
			return '(\'' . implode('\',\'', $id) . '\')';
		}
		
		/**
		*  Same as bindObject but done on a whole collection
		*  @method bindObjects
		*  @protected
		*  @param {Array} data The data to bind
		*  @param {String} dataClass The name of the data class (string to bind)
		*  @param {Array} [prependMaps=null] The array of maps to prepend to the variables used for 
		*		prepending a path or directory to a file/path/url
		*  @return {Array} The data object
		*/
		protected function bindObjects($data, $dataClass, $prependMaps=null)
		{
			$objects = array();
			// Loop through all of the data objects
			// and create a data object to match the data content
			for($i = 0; $i < count($data); $i++)
			{
				array_push(
					$objects, 
					$this->bindObject(
						$data[$i], 
						$dataClass, 
						$prependMaps, 
						false
					)
				);
			}
			unset($data);
			return $objects;
		}

		/**
		*  Bind an associate array to a data object
		*  @method bindObject
		*  @protected
		*  @param {Array} data The data to bind
		*  @param {String} dataClass The name of the data class (string to bind)
		*  @param {Array} [prependMaps=null] The array of maps to prepend to the variables used for 
		*		prepending a path or directory to a file/path/url
		*  @param {Boolean} [useFirstRow=true] If to just return the row in a return,
		*		only getting one item from a mysql array call
		*  @return {mixed} The typed data object
		*/
		protected function bindObject($data, $dataClass, $prependMaps=null, $useFirstRow=true)
		{
			if (!$data) return;

			if (!class_exists($dataClass)) 
			{
				error('Required class \''.$dataClass.'\' before it can be used to bind.');
				return;
			}

			if (count($data) && $useFirstRow) $data = $data[0];

			$obj = new $dataClass;
			$vars = get_object_vars($obj);

			foreach($vars as $name=>$value)
			{
				if (isset($data[$name]))
				{
					$obj->$name = $data[$name];

					// If there is a prepend mapping (such as a folder url path)
					if (isset($prependMaps[$name]))
					{
						$obj->$name = $prependMaps[$name] . $obj->$name;
					}
				}
			}
			return $obj;
		}
	}
}