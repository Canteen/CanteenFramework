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
	abstract class Service extends CanteenBase
	{		
		/**
		*  The name of the service alias
		*  @property {String} alias
		*/
		public $alias;

		/** 
		*  The registered list of services by alias
		*  @property {Dictionary} registerd
		*  @private
		*  @static
		*/
		private static $_registered = [];
		
		/**
		*  The mappings used to prepend the data object paths
		*  @property {Array} mappings
		*  @private
		*/
		private $mappings = [];
		
		/**
		*  Get a particular service by alias
		*  @method get
		*  @static
		*  @param {String} alias The _registered alias or class name
		*  @return {Service} The instance of the service
		*/
		public static function get($alias)
		{		
			if (!isset(self::$_registered[$alias]))
			{
				throw new CanteenError(CanteenError::INVALID_SERVICE_ALIAS, $alias);
			}
			return self::$_registered[$alias];
		}
		
		/**
		*  Get all the service aliases
		*  @method getAll
		*  @static
		*  @return {Array} The collection of string aliases
		*/
		public static function getAll()
		{
			return self::$_registered;
		}
		
		/**
		*  Add a single service alias
		*  @method register
		*  @static
		*  @param {String} alias The service alias
		*  @param {Service} service The Service object
		*/
		public static function register($alias, Service $service)
		{
			if (isset(self::$_registered[$alias]))
			{
				throw new CanteenError(CanteenError::TAKEN_SERVICE_ALIAS, $alias);
			}
			self::$_registered[$alias] = $service;
			$service->alias = $alias;
			return $service;
		}

		/**
		*  Add for use on the Gateway, these methods can be 
		*  called from javascript. For an example see the
		*  `TimeService` class.
		*  @method gateway
		*  @param {String} call The URI path to call from the gateway
		*  @param {String} method The name of the method
		*  @param {int} [privilege=Privilege::ANONYMOUS] The minimum privilege needed
		*     for the client to access this method.
		*  @return {Service} Return the instance of this for chaining
		*/
		public function gateway($call, $method, $privilege=Privilege::ANONYMOUS)
		{
			$this->site->gateway->register($call, [$this, $method], $privilege);
			return $this;
		}

		/**
		*  You can run to make sure a process requires a particular privilege
		*  @method privilege
		*  @protected
		*  @param {int} [required=Privilege::GUEST] The privilege level required, default is anonymous
		*/
		protected function privilege($required=Privilege::GUEST)
		{
			if ($this->settings->userPrivilege < $required)
			{
				throw new UserError(UserError::INSUFFICIENT_PRIVILEGE);
			}
		}

		/**
		*  Get the method/function name of the caller function
		*  @method getCaller
		*  @protected
		*  @param {int} [ignore=1] The depth of the call, default is the immediate function
		*   before this one is called
		*  @return {String|Array} The name of the method
		*/
		protected function getCaller($ignore=1)
		{
			// Add one to the depth to get the thing before
			$trace = $this->getSimpleStack(1, $ignore + 1);
			return ifsetor($trace[0]['function'], null);
		}

		/**
		*  Get the method/function name of the caller function
		*  @method getStack
		*  @protected
		*  @param {int} [limit=0] The number of stack items to return, default is all
		*  @param {int} [ignore=1] The number of stack levesl to ignore, default is the immediate function
		*   before this one is called.
		*  @return {String|Array} The name of the method or full trace
		*/
		private function getSimpleStack($limit = 0, $ignore = 1)
		{
			// We increase the starting depth to always ignore this method
			$ignore++;

			// Version 5.4+ has a limit
			if (version_compare(PHP_VERSION, '5.4.0') >= 0)
				$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, ($limit > 0 ? $limit + $ignore : 0));
			// Don't include the arguments if 5.3.6+
			else if (version_compare(PHP_VERSION, '5.3.6')  >= 0)
				$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			// Older versions of PHP
			else
				$trace = debug_backtrace();

			// Remove any preceeding levels from the stack
			$trace = array_slice($trace, $ignore, $limit);

			// Remove all the crap we don't need
			foreach($trace as $i=>$stack)
			{
				unset(
					$trace[$i]['object'], 
					$trace[$i]['args'],
					$trace[$i]['type'],
					$trace[$i]['line'],
					$trace[$i]['file']
				);
			}
			return $trace;
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
			$id = !is_array($id) ? [$id] : $id;
			$this->verify($id, $validationType);
			return '(\'' . implode('\',\'', $id) . '\')';
		}
		
		/**
		*  Same as bindObject but done on a whole collection
		*  @method bindObjects
		*  @protected
		*  @param {Array} data The data to bind
		*  @param {String} dataClass The name of the data class (string to bind)
		*  @param {Array} [prepends=null] The array of maps to prepend to the variables used for 
		*		prepending a path or directory to a file/path/url
		*  @return {Array} The data object
		*/
		protected function bindObjects($data, $dataClass, $prepends=null)
		{
			$objects = [];
			// Loop through all of the data objects
			// and create a data object to match the data content
			for($i = 0; $i < count($data); $i++)
			{
				array_push(
					$objects, 
					$this->bindObject(
						$data[$i], 
						$dataClass, 
						$prepends, 
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
		*  @param {Array} [prepends=null] The array of maps to prepend to the variables used for 
		*		prepending a path or directory to a file/path/url
		*  @param {Boolean} [useFirstRow=true] If to just return the row in a return,
		*		only getting one item from a mysql array call
		*  @return {mixed} The typed data object
		*/
		protected function bindObject($data, $dataClass, $prepends=null, $useFirstRow=true)
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
					if (isset($prepends[$name]))
					{
						$obj->$name = $prepends[$name] . $obj->$name;
					}
				}
			}
			return $obj;
		}

		/**
		*  Turn a collection of objects into a dictionary by a property name.
		*  @method dataMap
		*  @static
		*  @param {Array} results The objects collection
		*  @param {String} [key='id'] The name of the key to create the map on
		*  @return {Dictionary} The objects mapped by property
		*/
		static public function dataMap($results, $key='id')
		{
			$map = [];
			
			if ($results)
			{
				// Create a map of all the users by id
				foreach($results as $object)
				{
					if (is_object($object))
					{
						if (!isset($map[$object->$key]))
						{
							$map[$object->$key] = $object;
						}
					}
					else if (is_array($object))
					{
						if (isset($object[$key]) && !isset($map[$object[$key]]))
						{
							$map[$object[$key]] = $object;
						}
					}	
				}
			}
			return $map;
		}
	}
}