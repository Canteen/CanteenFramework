<?php

/**
*  @module Canteen
*/
namespace Canteen
{	
	use Canteen\Authorization\Authorization;
	use Canteen\Errors\CanteenError;
	use Canteen\Logger\Logger;
	use Canteen\Server\DeploymentStatus;
	use Canteen\Server\Gateway;
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
	use Canteen\Utilities\StringUtils;
	use Canteen\Database\Database;
	use Canteen\HTML5\SimpleList;
	use Canteen\Utilities\SettingsManager;
	use Canteen\Events\EventDispatcher;
	use \Exception;
	
	class Site extends EventDispatcher
	{	
		/** 
		*  The current version of the Canteen site 
		*  @property {String} VERSION  
		*  @static
		*  @final
		*/
		const VERSION = '1.1.0';
		
		/** 
		*  The current database version 
		*  @property {String} DB_VERSION
		*  @static
		*  @final
		*/
		const DB_VERSION = 103;
		
		/**
		*  The name of the main site template alias
		*  @property {String} MAIN_TEMPLATE
		*  @static
		*  @final
		*/
		const MAIN_TEMPLATE = 'CanteenTemplate';
		
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
		*  The dynamic page controllers with keys 'uri' and 'controller'
		*  @property {Array} _controllers
		*  @private
		*/
		private $_controllers;
		
		/** 
		*  The user authorization object, deals with login and session
		*  @property {Authorization} user
		*  @readOnly
		*/
		private $_user;
		
		/**
		*  The form factory handles the processing of form POST requests 
		*  @property {FormFactory} formFactory
		*  @readOnly
		*/
		private $_formFactory;
		
		/** 
		*  The site configuration an associative array in key => value pairs 
		*  @property {SettingsManager} settings
		*  @readOnly
		*/
		private $_settings;

		/** 
		*  The Profiler is use for debugging performance issues, SQL speed
		*  @property {Profiler} profiler
		*  @readOnly
		*/
		private $_profiler;

		/** 
		*  The Gateway is used for client connection to services or other data
		*  @property {Gateway} gateway
		*  @readOnly
		*/
		private $_gateway;
		
		/**
		*  The template parser
		*  
		*	// Add a template to the site
		*	$site->parser->addTemplate('Footer', 'Templates/Footer.html');
		*	
		*	// Or add a JSON manifest with an array of template files
		* 	$site->parser->addManifest('templates.json');
		*  
		*  @property {Parser} parser
		*  @readOnly
		*/
		private $_parser;
		
		/** 
		*  The instance of the server cache 
		*  @property {ServerCache} cache
		*  @readOnly
		*/
		private $_cache;
		
		/**
		*  The instance of the database for fetching data using SQL
		*  @property {Database} db
		*  @readOnly
		*/
		private $_db;
		
		/** 
		*  The fatal error
		*  @property {Dictionary} _fatalError
		*  @private
		*/
		private static $_fatalError = null;
		
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
		const MIN_PHP_VERSION = '5.4.0';
		
		/** 
		*  The starting time to keep track of buildtime
		*  @property {int} startTime
		*/
		public $startTime;
		
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
		*	// Externally load the deployment settings from a PHP file (default)
		*	$site = new Canteen\Site('config.php');
		*  
		*	// Or set the single deployment settings directly
		*	$site = new Canteen\Site([
		*		'dbUsername' => 'user',
		*		'dbPassword' => 'pass1234',
		*		'dbName' => 'my_settingsbase',
		*  	]);
		*	
		* 	// Or create different deployments of the site
		*	// for different domains where the site is hosted
		*	$site = new Canteen\Site([
		*  		// our local site accessible from http://localhost
		*  		[
		*  			'level' => DeploymentStatus::LOCAL,
		*  			'domain' => 'localhost',
		*			'dbUsername' => 'root',
		*			'dbPassword' => '',
		*			'dbName' => 'my_settingsbase',
		*  			'debug' => true
		*  		],
		*  		// Our dev site accessible from http://dev.example.com
		*  		[
		*  			'level' => DeploymentStatus::DEV,
		*  			'domain' => 'dev.example.com',
		*  			'dbUsername' => 'user',
		*			'dbPassword' => 'pass1234',
		*			'dbName' => 'my_dev_settingsbase',
		*  			'debug' => true
		*  		],
		*  		// Our live site accessible from http://example.com
		*  		[
		*  			'level' => DeploymentStatus::LIVE,
		*  			'domain' => 'example.com',
		*  			'dbUsername' => 'user',
		*			'dbPassword' => 'pass1234',
		*			'dbName' => 'my_settingsbase',
		*  			'minify' => true,
		*  			'compress' => true,
		*  			'cacheEnabled' => true
		*  		]
		* 	]);
		*		
		*	// Echo out the page result
		*	$site->render();
		*  
		*  @class Site
		*  @constructor
		*  @param {String|Array|Dictionary} [settings='config.php'] The path to the settings PHP, the collection of
		*			deployment settings, or the single deployment dictionary.
		*  @param {String} settings.dbUsername The username for the database
		*  @param {String} settings.dbPassword The password for the database
		*  @param {String|Dictionary} settings.dbName The name of the database or collection of aliases and databases ('default' is required)
		*  @param {String} [settings.dbHost='localhost'] The name of the database host
		*  @param {int} [settings.level=DeploymentStatus::LIVE] The deployment level of the site, see DeploymentStatus class for more info
		*  @param {Array|String} [settings.domain='*'] Single domain or collection of domains acceptable for this deployment to run
		*  @param {Boolean} [settings.debug=false] The debug mode
		*  @param {Boolean} [settings.cacheEnabled=false] If the cache is enabled
		*  @param {Boolean} [settings.compress=false] If the site should be compressed with gzip
		*  @param {Boolean} [settings.minify=false] If the output page should be minified
		*  @param {String} [settings.cacheDirectory=null] The directory for storing file cache if Memcache isn't available
		*  @param {String} [callerPath=null] The path to the root script of the site, useful if we
		*     are inheriting this class.
		*/
		public function __construct($settings='config.php', $callerPath=null)
		{		
			if ($callerPath === null)
			{
				$bt = debug_backtrace();
				$callerPath = dirname($bt[0]['file']).'/';
				unset($bt);
			}
			
			// Save singleton
			self::$_instance = $this;
			
			// Microseconds of start
			$this->startTime = microtime(true);
			
			// Create the object to handle forms
			$this->_formFactory = new FormFactory();
			
			// Setup the parser to render markup templates
			$this->_parser = new Parser();
			
			// Setup the settings manager
			$this->_settings = new SettingsManager();

			// Create the new gateway
			$this->_gateway = new Gateway();
			
			// Register is the caller path, internal path 
			$this->_settings->addSetting('callerPath', $callerPath);
			
			// Load the canteen templates
			$this->_parser->addManifest(__DIR__.'/Templates/templates.json');
			
			// Initialize the logger
			if (class_exists('Canteen\Logger\Logger'))
			{
				Logger::init();
			}
			
			try
			{
				$this->setup($settings);
			}
			catch(Exception $e)
			{
				$this->fatalError($e);
			}
		}

		/**
		*  Create a fatalError
		*  @method fatalError
		*  @param {Exception} e The caught exception
		*/
		public function fatalError(Exception $e)
		{
			if (!self::$_fatalError)
			{
				self::$_fatalError = ($e instanceof CanteenError) ? 
					$e->getResult():
					CanteenError::convertToResult($e);
			}
		}
		
		/**
		*  Finish setting up the site
		*  @method setup
		*  @private
		*  @param {String|Array|Dictionary} settings The deployment json settings path
		*/
		private function setup($settings)
		{			
			// Check for the version of PHP required to do the autoloading/namespacing
			if (version_compare(self::MIN_PHP_VERSION, PHP_VERSION) >= 0) 
			{
				throw new CanteenError(CanteenError::INSUFFICIENT_PHP, [PHP_VERSION, self::MIN_PHP_VERSION]);
			}
			
			// Check that the settings exists
			if (is_string($settings) && !file_exists($settings))
			{
				$this->_formFactory->startup('Canteen\Forms\SetupForm');
				die($this->_parser->template('Setup',
					[
						'formFeedback' => $this->_formFactory->getFeedback(),
						'configFile' => basename($settings)
					]
				));
			}
			
			// Check the domain for the current deployment level 
			$status = new DeploymentStatus($settings);
			
			// client, renderable, deletable, writeable
			$this->_settings->addSettings($status->settings)
				->access('fullPath', SETTING_RENDER)
				->access('local', SETTING_CLIENT | SETTING_RENDER)
				->access('host', SETTING_CLIENT | SETTING_RENDER)
				->access('basePath', SETTING_CLIENT | SETTING_RENDER)
				->access('baseUrl', SETTING_CLIENT)
				->access('uriRequest', SETTING_CLIENT)
				->access('queryString', SETTING_CLIENT | SETTING_RENDER)
				->access('debug', SETTING_CLIENT | SETTING_RENDER);
			
			// Debug mode most be on in order to profile
			$profiler = false;
			if (class_exists('Canteen\Profiler\Profiler') 
				&& $this->_settings->debug 
				&& ifsetor($_GET['profiler']) == 'true')
			{
				$this->_profiler = new Profiler($this->_parser);	
				$profiler = $this->_profiler;
				$profiler->start('Canteen Setup');

				// Assign the profiler to the parser
				$this->_parser->setProfiler($profiler);
			}
			
			// Set the error reporting if we're set to debug
			error_reporting($this->_settings->debug ? E_ALL : 0);
			
			// Turn on or off the logger
			if (class_exists('Canteen\Logger\Logger'))
			{
				Logger::instance()->enabled = $this->_settings->debug;	
			}
			
			// Setup the cache
			$this->_cache = new ServerCache(
				$this->_settings->cacheEnabled, 
				$this->_settings->cacheDirectory
			);
			
			// Create the non-database services
			new TimeService;
			
			if ($profiler) $profiler->start('Database Connect');
			
			if ($this->_settings->existsThrow('dbHost', 'dbUsername', 'dbPassword', 'dbName'))
			{
				$this->_db = new Database(
					$this->_settings->dbHost,
					$this->_settings->dbUsername,
					$this->_settings->dbPassword,
					$this->_settings->dbName
				);
					
				// Assign the server cache to the database
				$this->_db->setCache($this->_cache);
				
				// Setup the database profiler calls
				if ($profiler)
				{
					$this->_db->profilerStart = [$profiler, 'sqlStart'];
					$this->_db->profilerStop = [$profiler, 'sqlEnd'];
				}
			}
			
			// If the database is connected
			if ($this->_db && $this->_db->isConnected())
			{
				$this->_formFactory->startup('Canteen\Forms\InstallerForm');
				
				// Check for the installation process make it 
				// easier to start Canteen for the  first time
				$installed = ifsetor($_COOKIE['installed'], false);
				
				// Check to see if we're installed already
				if (!$installed)
				{
					if (!$this->_db->tableExists('config'))
					{
						die($this->_parser->template('Installer', 
							['formFeedback' => $this->_formFactory->getFeedback()]
						));
					}
					else
					{
						// Table already exists, don't need to keep checking
						$_COOKIE['installed'] = true;
					}
				}
				
				$service = Service::register('config', new ConfigService);
				
				// Add the configuration db assets
				// Give all settings global render access and changability
				$service->registerSettings();

				// Add the main site template
				$this->_parser->addTemplate(self::MAIN_TEMPLATE, 
					$this->settings->callerPath . $this->_settings->templatePath);
				
				// Process database changes here
				$this->_formFactory->startup('Canteen\Forms\DatabaseForm');
				
				// Check for database updates
				$this->isDatabaseUpdated('dbVersion', self::DB_VERSION, __DIR__.'/Upgrades/');
				
				// Create the services that require the database
				Service::register('page', new PageService);
				Service::register('user', new UserService);
			}
			
			if ($profiler) $profiler->end('Database Connect');
			
			if ($profiler) $profiler->start('Authorization');
			
			// Create a new user
			$this->_user = new Authorization();
			
			// Add to the manager and allow render access for loggedIn and user name
			$this->_settings->addSettings($this->_user->settings)
				->access('loggedIn', SETTING_RENDER)
				->access('userFullname', SETTING_RENDER);
			
			if ($profiler) $profiler->end('Authorization');
			
			// Set the globals
			$this->_controllers = [];
			$this->addController('admin', 'Canteen\Controllers\AdminController');
			$this->addController('admin/users', 'Canteen\Controllers\AdminUserController');
			$this->addController('admin/pages', 'Canteen\Controllers\AdminPageController');
			$this->addController('admin/password', 'Canteen\Controllers\AdminPasswordController');
			$this->addController('admin/config', 'Canteen\Controllers\AdminConfigController');
			$this->addController('forgot-password', 'Canteen\Controllers\ForgotPasswordController');
			
			if ($this->_settings->debug)
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
			
			if ($profiler) $profiler->end('Canteen Setup');
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
				$this->_controllers[] = [
					'uri' => $pageUri,
					'controller' => $controllerClassName
				];
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
			try
			{
				$version = $this->_settings->$variableName;
			}
			catch(Exception $e)
			{
				$version = 1;
			}

			// The database is up-to-date
			if ($version == $targetVersion) return;
						
			// Keep checking to see if we have updates
			// will do successive updates
			if ($version < $targetVersion && file_exists($updatesFolder.$version.'.php'))
			{
				// Specifically ask for an database update
				die($this->_parser->template('UpgradeDatabase', [
					'targetVersion' => $targetVersion,
					'version' => $version,
	 				'updatesFolder' => $updatesFolder,
					'variableName' => $variableName,
					'formFeedback' => $this->_formFactory->getFeedback()
				]));
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
		*  Get the current page markup, echoes on the page
		*  @method render
		*/
		public function render()
		{
			if (!($result = $this->readyToProceed()))
			{
				try
				{
					$builder = new PageBuilder();
					$result = $builder->handle();
				}
				catch(Exception $e)
				{
					$this->fatalError($e);
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
				
				$data = [
					'type' => 'fatalError',
					'debug' => $debug
				];
				
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
						$data['stackTrace'] = new SimpleList($data['stackTrace'], null, 'ol');
					}
					$result = $this->_parser->template('FatalError', $data);
					$this->_parser->removeEmpties($result);
				}
			}
			return $result;
		}
		
		/**
		*  Set the configuration setting
		*  @method addSetting
		*  @param {String} name The name of the item to set
		*  @param {String} value The value to set
		*  @param {Boolean} [client=true] If the setting should be added to the global client JS settings
		*  @param {Boolean} [renderable=true] If the setting can be rendered 
		*/
		public function addSetting($name, $value, $clientGlobal=true, $renderable=true)
		{
			$this->_settings->addSetting($name, $value, $clientGlobal, $renderable);
		}		
		
		/**
		*  Private getters
		*/
		public function __get($name)
		{
			$default = '_'.$name;
			switch($name)
			{
				case 'parser' :
				case 'formFactory' :		
				case 'db' :
				case 'cache' :
				case 'user' :
				case 'settings' :
				case 'gateway' :
				case 'profiler' : 
					return $this->$default;
			}
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
					[self::VERSION, $required]
				);
				self::$_fatalError = $e->getResult();
			}
		}
	}
}