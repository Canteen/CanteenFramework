<?php

/**
*  @module Canteen
*/
namespace Canteen
{	
	use Canteen\Authorization\Authorization;
	use Canteen\Errors\CanteenError;
	use Canteen\Logger\Logger;
	use Canteen\Parser\TemplateLoader;
	use Canteen\Server\DeploymentStatus;
	use Canteen\Profiler\Profiler;
	use Canteen\Server\ServerCache;
	use Canteen\Forms\FormFactory;
	use Canteen\Services\TimeService;
	use Canteen\Services\ConfigService;
	use Canteen\Services\PageService;
	use Canteen\Services\UserService;
	use Canteen\Services\Service;
	use Canteen\Parser\PageBuilder;
	use Canteen\Parser\Parser;
	use Canteen\Utilities\CanteenBase;
	use Canteen\Utilities\StringUtils;
	use Canteen\Utilities\Templates;
	use Canteen\Database\Database;
	use Canteen\HTML5\SimpleList;
	use \Exception;
	
	class Site extends CanteenBase
	{	
		/** 
		*  The current version of the Canteen site 
		*  @property {String} VERSION  
		*  @static
		*  @final
		*/
		const VERSION = '1.4.0';
		
		/** 
		*  The current database version 
		*  @property {String} DB_VERSION
		*  @static
		*  @final
		*/
		const DB_VERSION = 102;
		
		/** 
		*  The name of the gateway page 
		*  @property {String} gatewayUri
		*  @default gateway
		*/
		public $gatewayUri = 'gateway';
		
		/** 
		*  The uri name for the service browser
		*  @property {String} browserUri
		*  @default browser
		*/
		public $browserUri = 'browser';
		
		/** 
		*  The logout uri 
		*  @property {String} logoutUri
		*  @default logout
		*/
		public $logoutUri = 'logout';
		
		/** 
		*  The site configuration an associative array in key => value pairs 
		*  @property {Dictionary} _data
		*  @private
		*/
		private $_data;
		
		/** 
		*  The dynamic page controllers with keys 'uri' and 'controller'
		*  @property {Array} _controllers
		*  @private
		*/
		private $_controllers;
		
		/** 
		*  The user authorization object, deals with login and session
		*  @property {Authorization} _authorization
		*  @private
		*/
		private $_authorization;
		
		/** 
		*  The form factory handles the processing of form POST requests 
		*  @property {FormFactory} _formFactory
		*  @private
		*/
		private $_formFactory;
		
		/** 
		*  The instance of the server cache 
		*  @property {ServerCache} _cache
		*  @private
		*/
		private $_cache;
		
		/** 
		*  The instance of the database
		*  @property {Database} _db
		*  @private
		*/
		private $_db;
		
		/** 
		*  The fatal error
		*  @property {Dictionary} _fatalError
		*  @private
		*/
		private static $_fatalError = null;
		
		/**
		*  The template loader
		*  @private {TemplateLoader} _loader 
		*  @private
		*/
		private $_loader;
		
		/** 
		*  The singleton instance 
		*  @property {Canteen} _instance
		*  @private
		*  @static
		*/
		private static $_instance;
		
		/** 
		*  The minimum PHP version required to run termite 
		*  @property {String} MIN_PHP_VERSION
		*  @final
		*  @static
		*/
		const MIN_PHP_VERSION = '5.3.0';
		
		/**
		*  Get the singleton instance
		*  @method instance
		*  @static
		*  @return {Canteen} The singleton instance of ServerCache
		*/
		public static function instance()
		{
			return self::$_instance;
		}
		
		/**
		*  This is the main class of the framework that needs to be implemented in order to use. Canteen
		*  is a backend which provides a JSON server, Database connect, template engine, User authentication
		*  and many other features for building very dynamic data-driven websites. 
		*	
		*	// These are the minimum settings
		*  	// you need to setup Canteen, just the database stuff
		*	$site = new Canteen\Site(array(
		*		'dbUsername' => 'user',
		*		'dbPassword' => 'pass1234',
		*		'dbName' => 'my_database',
		*  	));
		*	
		* 	// Or create different deployments of the site
		*	// for different domains where the site is hosted
		*	$site = new Canteen\Site(array(
		*  		// our local site accessible from http://localhost
		*  		array(
		*  			'level' => DeploymentStatus::LOCAL,
		*  			'domain' => 'localhost',
		*			'dbUsername' => 'root',
		*			'dbPassword' => '',
		*			'dbName' => 'my_database',
		*  			'debug' => true
		*  		),
		*  		// Our dev site accessible from http://dev.example.com
		*  		array(
		*  			'level' => DeploymentStatus::DEV,
		*  			'domain' => 'dev.example.com',
		*  			'dbUsername' => 'user',
		*			'dbPassword' => 'pass1234',
		*			'dbName' => 'my_dev_database',
		*  			'debug' => true
		*  		),
		*  		// Our live site accessible from http://example.com
		*  		array(
		*  			'level' => DeploymentStatus::LIVE,
		*  			'domain' => 'example.com',
		*  			'dbUsername' => 'user',
		*			'dbPassword' => 'pass1234',
		*			'dbName' => 'my_database',
		*  			'minify' => true,
		*  			'compress' => true,
		*  			'cacheEnabled' => true
		*  		)
		* 	));
		*  
		*	// OR, externally load the deployment settings 
		*	// from a PHP file
		*	$site = new Canteen\Site('settings.php');
		*		
		*	// Echo out the page result
		*	$site->render();
		*  
		*  @class Site 
		*  @extends CanteenBase
		*  @constructor
		*  @param {String|Array|Dictionary} [settings='config.php'] The path to the settings PHP, the collection of
		*            deployment settings, or the single deployment dictionary.
		*  @param {String} settings.dbUsername The username for the database
		*  @param {String} settings.dbPassword The password for the database
		*  @param {String|Dictionary} settings.dbName The name of the database or collection of aliases and databases ('default' is required)
		*  @param {String} [settings.dbHost='localhost'] The name of the database host
		*  @param {int} [settings.level=DeploymentStatus::LIVE] The deployment level of the site, see DeploymentStatus class for more info
		*  @param {Array} [settings.domains=null] Collection of domains acceptable for this deployment to run
		*  @param {String} [settings.domain='*'] The domain that's acceptable for this deployment to run, default is anywhere
		*  @param {Boolean} [settings.debug=false] The debug mode
		*  @param {Boolean} [settings.cacheEnabled=false] If the cache is enabled
		*  @param {Boolean} [settings.compress=false] If the site should be compressed with gzip
		*  @param {Boolean} [settings.minify=false] If the output page should be minified
		*  @param {String} [manifestPath=null] The manifest path for autoloading
		*  @param {String} [cacheDirectory=null] The directory for storing file cache if Memcache isn't available
		*/
		public function __construct($settings='config.php', $manifestPath=null, $cacheDirectory=null)
		{		
			$bt = debug_backtrace();
			define('CALLER_PATH', dirname($bt[0]['file']).'/');
			unset($bt);
			
			// Define the system path 
			define('CANTEEN_PATH', __DIR__.'/');
			define('MAIN_TEMPLATE', 'MainTemplate');
			
			self::$_instance = $this;
			
			try
			{
				$this->setup($settings, $manifestPath, $cacheDirectory);
			}
			catch(CanteenError $e)
			{
				self::$_fatalError = $e->getResult();
			}
			catch(Exception $e)
			{
				self::$_fatalError = CanteenError::convertToResult($e);
			}
		}
		
		/**
		*  Finish setting up the site
		*  @method setup
		*  @private
		*  @param {String|Array|Dictionary} settings The deployment json settings path
		*  @param {String} [manifestPath=null] The manifest path for autoloading
		*  @param {String} [cacheDirectory=null] The directory for storing file cache if Memcache isn't available
		*/
		private function setup($settings, $manifestPath=null, $cacheDirectory=null)
		{
			Logger::init();
			
			// Check for the version of PHP required to do the autoloading/namespacing
			if (version_compare(self::MIN_PHP_VERSION, PHP_VERSION) >= 0) 
			{
				throw new CanteenError(CanteenError::INSUFFICIENT_PHP, 'Minimum PHP version: '.self::MIN_PHP_VERSION);
			}
			
			// Check the domain for the current deployment level 
			$status = new DeploymentStatus($settings);
			$this->_data = $status->data;
			
			// Debug mode most be on in order to profile
			$profiler = false;
			if (DEBUG)
			{
				$profiler = (ifsetor($_GET['profiler']) == 'true');
			}
			
			// Make sure it's not already defined, possibly in the settings
			if (!defined('PROFILER'))
			{
				define('PROFILER', $profiler);
			}
			
			if ($profiler) 
			{				
				Profiler::enable();
				Profiler::start('Canteen Setup');
			}
			
			// Set the error reporting if we're set to debug
			error_reporting(DEBUG ? E_ALL : 0);
			
			// Turn on or off the logger
			Logger::instance()->enabled = DEBUG;			
			
			// Setup the templates loader
			$this->_loader = new TemplateLoader();
			
			// Load the canteen templates
			$this->_loader->addManifest(CANTEEN_PATH . 'Templates/templates.json');			
			
			// Setup the cache
			$this->_cache = new ServerCache($cacheDirectory);
			
			// Create a new factory to intercept form requests
			$this->_formFactory = new FormFactory();
			
			// Create the non-database services
			new TimeService;
			
			if ($profiler) Profiler::start('Database Connect');
			
			if ($this->checkData('dbHost', 'dbUsername', 'dbPassword', 'dbName'))
			{
				$this->_db = new Database(
					$this->_data['dbHost'],
					$this->_data['dbUsername'],
					$this->_data['dbPassword'],
					$this->_data['dbName']);
					
				// Assign the server cache to the database
				$this->_db->setCache($this->_cache);
				
				// Setup the database profiler calls
				if ($profiler)
				{
					$profiler = 'Canteen\Profiler\Profiler';
					$this->_db->profilerStart = array($profiler, 'sqlStart');
					$this->_db->profilerStop = array($profiler, 'sqlEnd');
				}
			}
			
			// If the database is connected
			if ($this->_db && $this->_db->isConnected())
			{
				$this->_formFactory->startup(
					'Canteen\Forms\DatabaseUpdate',
					'Canteen\Forms\Installer'
				);
				
				// Check for the installation process make it 
				// easier to start Canteen for the  first time
				$installed = ifsetor($_SESSION['installed'], false);
				
				// Check to see if we're installed already
				if (!$installed)
				{
					if (!$this->_db->tableExists('config'))
					{
						die(Parser::getTemplate('Installer', array(
							'formFeedback' => $this->_formFactory->getFeedback()
						)));
					}
					else
					{
						// Table already exists, don't need to keep checking
						$_SESSION['installed'] = true;
					}
				}
				
				$service = new ConfigService;
				
				// Add the configuration db assets
				$this->_data = array_merge($service->getAll(), $this->_data);
				
				// Check for default index page, site title, content path, template
				$this->checkData($service->getProtectedNames());
				
				// Add the main site template
				$this->_loader->addTemplate(MAIN_TEMPLATE, CALLER_PATH . $this->_data['templatePath']);
				
				// Check for database updates
				$this->isDatabaseUpdated('dbVersion', self::DB_VERSION, CANTEEN_PATH.'Upgrades/');
				
				// Create the services that require the database
				new PageService;
				new UserService;
			}
			
			if ($profiler) Profiler::end('Database Connect');
			
			if ($profiler) Profiler::start('Authorization');
			
			// Create a new user
			$this->_authorization = new Authorization();
			$this->_data = array_merge(
				$this->_authorization->getData(),
				$this->_data
			);
			
			if ($profiler) Profiler::end('Authorization');
			
			// Setup an additional manifest file
			if ($manifestPath !== null) 
			{
				if ($profiler) Profiler::start('Register Template Manifest');
				$this->_loader->addManifest($manifestPath);
				if ($profiler) Profiler::end('Register Template Manifest');	
			}
			
			// Set the globals
			$this->_controllers = array();
			$this->addController('admin', 'Canteen\Controllers\AdminController');
			$this->addController('admin/users', 'Canteen\Controllers\AdminUsersController');
			$this->addController('admin/pages', 'Canteen\Controllers\AdminPagesController');
			$this->addController('admin/password', 'Canteen\Controllers\AdminPasswordController');
			$this->addController('admin/config', 'Canteen\Controllers\AdminConfigController');
			$this->addController('forgot-password', 'Canteen\Controllers\ForgotPasswordController');
			
			if (DEBUG)
			{
				// URL clear for the cache
				if (ifsetor($_GET['flush']) == 'all')
				{
					$this->_cache->flush();
				}
				else if (ifsetor($_GET['flush']) == 'database')
				{
					if ($this->_db) $this->_db->flush();
				}
			}
			
			if ($profiler) Profiler::end('Canteen Setup');
		}
			
		/**
		*  Store a page user function call for dynamic pages
		*  @method addController
		*  @param {String|RegExp|Array} pageUri The page ID of the dynamic page (array for multiple items) or can be an regular expression
		*  @param {String} controllerClassName The user class to call
		*/
		public function addController($pageUri, $controllerClassName)
		{
			if (is_array($pageUri))
			{
				foreach($pageUri as $p)
				{
					$this->addController($p, $controllerClassName);
				}
			}
			else
			{
				// If we're using the catch-all star
				if (!StringUtils::isRegex($pageUri) && preg_match('/\*/', $pageUri))
				{
					$pageUri = preg_replace('/\//', '\/', $pageUri);
					$pageUri = preg_replace('/\*/', '[a-zA-Z0-9\-_\/]+', $pageUri);
					$pageUri = '/^'.$pageUri.'$/';
				}
				$this->_controllers[] = array(
					'uri' => $pageUri,
					'controller' => $controllerClassName
				);
			}
		}
		
		/**
		*  Check to make sure the database is up-to-date
		*  @method isDatabaseUpdated
		*  @param {String} variableName The name of the config variable
		*  @param {String} targetVersion The target version the database should be at
		*  @param {String} updatesFolder The folder containing the update scripts
		*/
		public function isDatabaseUpdated($variableName, $targetVersion, $updatesFolder)
		{		
			$version = ifsetor($this->_data[$variableName], 1);

			// The database is up-to-date
			if ($version == $targetVersion) return;
						
			// Keep checking to see if we have updates
			// will do successive updates
			if ($version < $targetVersion && file_exists($updatesFolder.$version.'.php'))
			{
				// Specifically ask for an database update
				die(Parser::getTemplate('UpgradeDatabase', array(
					'targetVersion' => $targetVersion,
					'version' => $version,
	 				'updatesFolder' => $updatesFolder,
					'variableName' => $variableName,
					'formFeedback' => $this->_formFactory->getFeedback()
				)));
			}
		}
		
		/**
		*  Get a page controller by uri
		*  @method getController
		*  @param {String} pageUri The uri of the page
		*  @return {Controller} The controller matching the URI
		*/
		public function getController($pageUri)
		{
			foreach($this->_controllers as $c)
			{
				$uri = $c['uri'];
				
				// Check for regular expression and compare that to the page
				if (StringUtils::isRegex($uri) && preg_match($uri, $pageUri))
				{
					return $c['controller'];
				}
				else if ($uri == $pageUri)
				{
					return $c['controller'];
				} 
			}
			return null;
		}
		
		/**
		*  Add a collection of custom services
		*  @method addServices
		*  @param {Dictionary} aliases The dictionary of service aliases to class names
		*/
		public function addServices($aliases)
		{
			foreach($aliases as $alias=>$className)
			{
				$this->addService($alias, $className);
			}
		}
		
		/**
		*  Register a single custom service.
		*  @method addService
		*  @param {String} alias The service alias
		*  @param {String} className The full namespace and package class name. Must extend Canteen\Service\Service class.
		*/
		public function addService($alias, $className)
		{
			try
			{
				Service::addService($alias, $className);
			}
			catch(CanteenError $e)
			{
				self::$_fatalError = $e->getResult();
			}
		}
		
		/**
		*  Get the current page markup, echoes on the page
		*  @method render
		*  @param {Array} globalSettings Additional keys of global Canteen settings 
		*         to add to make accessible via the JavaScript Canteen.settings object.
		*/
		public function render($globalSettings=null)
		{
			if (!($result = $this->readyToProceed()))
			{
				try
				{
					if ($globalSettings && !is_array($globalSettings))
					{
						$globalSettings = func_get_args();
					}
					$builder = new PageBuilder($globalSettings);
					$result = $builder->handle();
				}
				catch(CanteenError $e)
				{
					self::$_fatalError = $e->getResult();
					$result = $this->readyToProceed();
				}
				catch(Exception $e)
				{
					self::$_fatalError = CanteenError::convertToResult($e);
					$result = $this->readyToProceed();
				}
			}
			echo $result;
		}
		
		/**
		*  See if there are any errors or warnings
		*  bail out if we need to
		*  @method readyToProceed
		*  @private
		*  @return {String} Assemble any fatal errors into a template, return null if no errors
		*/
		private function readyToProceed()
		{
			$result = null;
			
			if (self::$_fatalError != null)
			{
				$debug = ifconstor('DEBUG', true);
				$async = ifconstor('ASYNC_REQUEST', false);
				
				$data = array(
					'type' => 'fatalError',
					'debug' => $debug
				);
				
				$data = array_merge(self::$_fatalError, $data);
				
				if (!$debug) 
				{
					unset($data['stackTrace']);
					unset($data['file']);
				}
				
				if ($async)
				{
					$result = json_encode($data);
				}
				else
				{
					if ($debug)
					{
						$data['stackTrace'] = new SimpleList(
							$data['stackTrace'], null, 'ol');
					}
					$result = Parser::getTemplate('FatalError', $data);
					Parser::removeEmpties($result);
				}
			}
			return $result;
		}
		
		/**
		*  Set the configuration or data
		*  @method setData
		*  @param {String} name The name of the item to set
		*  @param {String} value The value to set
		*/
		public function setData($name, $value)
		{
			$this->_data[$name] = $value;
		}
		
		/**
		*  Get the data for name 
		*  @method getData
		*  @param {String} [name=null] The name of of the data property, no value returns all data properties
		*  @return {Array|String} Either a specific data item by name or all the data properties
		*/
		public function getData($name=null)
		{
			if ($name !== null)  return ifsetor($this->_data[$name], null);
			return $this->_data;
		}
		
		/**
		*  Get the user authorization
		*  @method getUser
		*  @return {Authorization} The Authorization instance
		*/
		public function getUser()
		{
			return $this->_authorization;
		}
		
		/**
		*  Get the server cache instance
		*  @method getCache
		*  @return {ServerCache} The ServerCache instance
		*/
		public function getCache()
		{
			return $this->_cache;
		}
		
		/**
		*  Get the instance of the database
		*  @method getDB
		*  @return {Database} The Database instance
		*/
		public function getDB()
		{
			return $this->_db;
		}
		
		/**
		*  Get the instance of the form factory
		*  @method getFormFactory
		*  @return {FormFactory} The FormFactory instance
		*/
		public function getFormFactory()
		{
			return $this->_formFactory;
		}
		
		/**
		*  Get the instance of the template loader
		*  
		*	// Add a template to the site
		*	$site->getLoader()->addTemplate('Footer', 'Templates/Footer.html');
		*	
		*	// Or add a JSON manifest with an array of template files
		* 	$site->getLoader()->addManifest('templates.json');
		*  
		*  @method getLoader
		*  @return {TemplateLoader} The TemplateLoader instance
		*/
		public function getLoader()
		{
			return $this->_loader;
		}
		
		/**
		*  Check that a version of Canteen is required to run
		*  @method requiresVersion
		*  @static
		*  @param {String} required The least required version to run
		*/
		public static function requiresVersion($required)
		{
			if (version_compare(self::VERSION, $required) < 0)
			{
				$e =  new CanteenError(
					CanteenError::INSUFFICIENT_VERSION, 
					'Required version: '.$required.', current version: '.self::VERSION
				);
				self::$_fatalError = $e->getResult();
			}
		}
		
		/**
		*  If multiple keys exists in an array
		*  @method checkData
		*  @private
		*  @param {String} args* The data names that are required
		*/
		private function checkData($args)
		{
			$keys = is_array($args) ? $args : func_get_args();
			$missing = array();
			foreach($keys as $key)
			{
				if (!isset($this->_data[$key]))
				{
					$missing[] = $key;
				}
			}
			
			if (count($missing))
			{
				throw new CanteenError(CanteenError::INVALID_DATA, implode(", ", $missing));
			}
			return true;
		}
	}
}