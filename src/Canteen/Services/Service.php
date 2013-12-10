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
		private static $_registered = array();
		
		/** 
		*  A map of the class name to registered alias 
		*  @property {Array} _registeredMaps
		*  @private
		*  @static
		*/
		private static $_registeredMaps = array();
		
		/** 
		*  A map of the class name to registered alias to class name 
		*  @property {Array} _registeredAliases
		*  @private
		*  @static
		*/
		private static $_registeredAliases = array();
		
		/**
		*  The mappings used to prepend the data object paths
		*  @property {Array} mappings
		*  @private
		*/
		private $mappings = array();

		/**
		*  The map of access controls by function name
		*  @property {Dictionary}
		*  @private
		*/
		private $_accessControls = array();
		
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
		*  Convenience check to see if the user has the privilege 
		*  to do a particular action.
		*  @method privilege
		*  @protected
		*  @param {int} [required=0] The privilege required, (default is guest privilege)
		*/
		protected function privilege($required=Privilege::GUEST)
		{
			if ($this->settings->local) return;
			
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
			if ($this->settings->local) return;
			
			// DEBUG_BACKTRACE_IGNORE_ARGS is only in PHP 5.3.6
			$trace = defined('DEBUG_BACKTRACE_IGNORE_ARGS') ? 
				debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS):
				debug_backtrace();

			// Simplest trace: this, service, caller
			// Complicated trace: this, access, accessDefault, __method, override
			// 0 is this class, 2-4 is the internal caller		
			$classes = is_array($classes) ? $classes : func_get_args();
			
			// Check if the class is anywhere in the trace stack
			// TODO Need to allow CustomService to call and check for the 
			// specific method called before
			foreach($trace as $i=>$stack)
			{
				// If the stack track is greater than for then we should bail
				// we are only concernd with the 2-4 level of depth
				if ($i > 5) break;

				if (isset($stack['class']) && in_array($stack['class'], $classes))
				{
					// bail out, proceed as normal
					return;
				}
			}
			throw new CanteenError(CanteenError::INTERNAL_ONLY);
		}
		
		/**
		*  Set and check the access control for a method this is important for preventing
		*  access to these methods that is undesirable, like someone using
		*  the JSON to edit/add/remove database entries.
		*
		*	// To set access controls
		* 	$this->access('removeContent', Privilege::ADMINISTRATOR);
		*   $this->access('updateContent', Privilege::GUEST, 'Site\Form\ContentUpdate');
		*   // or
		* 	$this->access(array(
		*		'removeContent' => Privilege::ADMINISTRATOR,
		*		'updateContent' => array(
		*			Privilege::GUEST, 
		*			'Site\Form\ContentUpdate'
		*		)
		*	));
		* 	// To check for access control
		* 	$this->access('removeContent');
		* 
		*  @method access
		*  @protected
		*  @param {String|Dictionary} [mapOrMethod=null] Either the method string 
		*    or a map of methods to an collection of controls, if null, then 
		*    does an access check for the current function called from
		*  @param {Array|String|int} [controls=null] The collection of controls
		*/
		protected function access($mapOrMethod=null, $controls=null)
		{
			// Process the map of controls
			if (is_array($mapOrMethod))
			{
				foreach ($mapOrMethod as $m => $c)
				{
					$this->access($m, $c);
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
			// Control run check
			else
			{
				// If there's not  a method specified, then 
				if ($mapOrMethod === null)
				{
					// DEBUG_BACKTRACE_IGNORE_ARGS is only in PHP 5.3.6
					$trace = defined('DEBUG_BACKTRACE_IGNORE_ARGS') ? 
						debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS):
						debug_backtrace();

					$mapOrMethod = ifsetor($trace[1]['function'], null);
				}

				// Bail out if there isn't an access control
				if (!isset($this->_accessControls[$mapOrMethod])) return;

				$control = $this->_accessControls[$mapOrMethod];
				
				// Check the privilege
				if ($control->privilege)
					$this->privilege($control->privilege);
				
				// Check the internal calls
				if (count($control->internals))
					$this->internal($control->internals);
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

	/**
	*  Used for internal purpose to keep track of method access controls
	*  such as requiring a privilege or being only called by another class
	*/
	class AccessControl
	{
		/** The name of the method */
		public $name;

		/** The privilege required to run this method, default is all */
		public $privilege = Privilege::ANONYMOUS;

		/** The collection of methods that can call this function */
		public $internals = array();

		/**
		*  Create the control
		*  @constructor
		*  @param {String} name The name of the control
		*  @param {Array|String|int} controls The collection of controls
		*/
		public function __construct($name, $controls)
		{
			$this->name = $name;

			if (!is_array($controls)) $controls = array($controls);

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