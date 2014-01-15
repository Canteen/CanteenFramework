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
		*  The registered list of services 
		*  @property {Array} registerd
		*  @private
		*  @static
		*/
		private static $_registered = [];
		
		/** 
		*  A map of the class name to registered alias 
		*  @property {Array} _registeredMaps
		*  @private
		*  @static
		*/
		private static $_registeredMaps = [];
		
		/** 
		*  A map of the class name to registered alias to class name 
		*  @property {Array} _registeredAliases
		*  @private
		*  @static
		*/
		private static $_registeredAliases = [];
		
		/**
		*  The mappings used to prepend the data object paths
		*  @property {Array} mappings
		*  @private
		*/
		private $mappings = [];

		/**
		*  The map of access controls by function name
		*  @property {Dictionary}
		*  @private
		*/
		private $_accessControls = [];
		
		/**
		*  Create the service
		*/
		public function __construct($alias)
		{
			if (isset(self::$_registered[$alias]))
			{
				warning("The service ('$alias') already exists, overwriting.");
			}
			$this->alias = $alias;
			self::$_registered[$alias] = $this;
			self::$_registeredMaps[get_class($this)] = $this;
			self::$_registeredAliases[$alias] = get_class($this);
		}
		
		/**
		*  Get a particular service by alias
		*  @method get
		*  @static
		*  @param {String} aliasOrClassName The _registered alias or class name
		*  @return {Service} The instance of the service
		*/
		public static function get($aliasOrClassName)
		{			
			$aliases = self::$_registeredAliases;
			
			// Check if the alias is _registered
			if (isset(self::$_registered[$aliasOrClassName]))
			{
				return self::$_registered[$aliasOrClassName];
			}
			// Check if the class is _registered
			else if (isset(self::$_registeredMaps[$aliasOrClassName]))
			{
				return self::$_registeredMaps[$aliasOrClassName];
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
			return self::$_registeredAliases;
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
			if (isset(self::$_registeredAliases[$alias]))
			{
				throw new CanteenError(CanteenError::TAKEN_SERVICE_ALIAS, $alias);
			}
			self::$_registeredAliases[$alias] = $className;
		}
		
		/**
		*  Set and check the access control for a method this is important for preventing
		*  access to these methods that is undesirable, like someone using
		*  the JSON to edit/add/remove database entries.
		*
		*	// To set access controls
		* 	$this->restrict('removeContent', Privilege::ADMINISTRATOR);
		*   $this->restrict('updateContent', Privilege::GUEST, 'Site\Form\ContentUpdate');
		*   // or
		* 	$this->restrict(array(
		*		'removeContent' => Privilege::ADMINISTRATOR,
		*		'updateContent' => [
		*			Privilege::GUEST, 
		*			'Site\Form\ContentUpdate'
		*		]
		*	));
		* 	// To check for access control within a method
		* 	$this->access();
		* 
		*  @method restrict
		*  @protected
		*  @param {String|Dictionary} [mapOrMethod] Either the method string 
		*	or a map of methods to an collection of controls.
		*  @param {Array|String|int} [controls=null] The collection of controls
		*  @return {Service} Return the instance of this for chaining
		*/
		protected function restrict($mapOrMethod, $controls=null)
		{
			// Process the map of controls
			if (is_array($mapOrMethod))
			{
				foreach ($mapOrMethod as $m => $c)
				{
					$this->restrict($m, $c);
				}
			}
			else if ($controls !== null)
			{
				// If the controls is an array or a series of items
				if (!is_array($controls))
				{
					$controls = func_get_args();
					array_shift($controls); //remove the method name
				}
				$this->_accessControls[$mapOrMethod] = new AccessControl($mapOrMethod, $controls);
			}
			return $this;
		}

		/**
		*  Check for restricted access. Access can be initialized with the restrict()
		*  method. 
		*  @method access
		*  @protected
		*  @param {String} method The name of the method to check the access for
		*  @return {Service} Return the instance of this for chaining
		*/
		protected function access($method=null)
		{
			// Ignore access controls if we're local
			if (!count($this->_accessControls) || $this->settings->local) return $this;
			
			// Get the method that called this function
			if ($method === null) $method = $this->getCaller();

			// Bail out if there isn't an access control
			if (!isset($this->_accessControls[$method])) return $this;

			$control = $this->_accessControls[$method];
			
			// Check the internal calls
			// this will override any privilege that's need
			// and is the more restrictive of the controls
			if (count($control->internals))
			{
				// 0 is Service Class, 1 is caller, 2-3 is the internal caller
				// If the stack track is greater than for then we should bail
				// we are only concernd with the 2-3 level of depth
				$trace = $this->getSimpleStack(3);

				// Check if the class is in the trace stack
				foreach($trace as $i=>$stack)
				{
					if (isset($stack['class']) && in_array($stack['class'], $control->internals))
					{
						// bail out, proceed as normal
						return $this;
					}
				}
				throw new CanteenError(
					CanteenError::INTERNAL_ONLY, 
					[$method, implode(', ', $control->internals)]
				);
			}

			// Check for adequate privilege
			if ($control->privilege)
			{
				$loggedIn = ifconstor('LOGGED_IN', false);
				$privilege = ifconstor('USER_PRIVILEGE', 0);

				if (!$loggedIn)
				{
					throw new UserError(UserError::LOGGIN_REQUIRED);
				}
				else if ($privilege < $control->privilege)
				{
					throw new UserError(UserError::INSUFFICIENT_PRIVILEGE);
				}
			}
			
			return $this;
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

	/**
	*  Used for internal purpose to keep track of method access controls
	*  such as requiring a privilege or being only called by another class
	*/
	class AccessControl
	{
		/** The name of the method */
		public $name;

		/** The privilege required to run this method, default is all */
		public $privilege = Privilege::GUEST;

		/** The collection of methods that can call this function */
		public $internals = [];

		/**
		*  Create the control
		*  @constructor
		*  @param {String} name The name of the control
		*  @param {Array|String|int} controls The collection of controls
		*/
		public function __construct($name, $controls)
		{
			$this->name = $name;

			if (!is_array($controls)) $controls = [$controls];

			foreach($controls as $c)
			{
				if (is_string($c))
				{
					$this->internals[] = $c;
				}
				else if(is_numeric($c))
				{
					$this->privilege = $c;
				}
			}
		}
	}
}