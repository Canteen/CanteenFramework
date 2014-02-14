<?php

/**
*  @module Canteen
*/
namespace Canteen
{
	use \Exception;
	use flight\Engine;
	use Canteen\Errors\CanteenError;
	use Canteen\HTML5\SimpleList;
	use Canteen\Server\DeploymentStatus;
	use Canteen\Logger\Logger;
	use Canteen\Services\TimeService;
	use Canteen\Services\ConfigService;
	use Canteen\Services\PageService;
	use Canteen\Services\UserService;

	// We need to initalize flight before we can extend Engine
	// kind of hacky but it's the best solution
	// because Flight doesn't support prs-* loading with Composer
	\Flight::init();
	
	class Site extends Engine
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
		*  The dynamic page controllers with keys 'uri' and 'controller'
		*  @property {Array} _controllers
		*  @private
		*/
		private $_controllers;

		/** 
		*  The starting time to keep track of buildtime
		*  @property {int} startTime
		*/
		public $startTime;

		/**
		*  The configuration option or file
		*  @property {Array|String} _config
		*  @private
		*/
		private $_config;
		
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
		*  @param {String|Array|Dictionary} [config='config.php'] The path to the settings PHP, the collection of
		*			deployment settings, or the single deployment dictionary.
		*  @param {String} config.dbUsername The username for the database
		*  @param {String} config.dbPassword The password for the database
		*  @param {String|Dictionary} config.dbName The name of the database or collection of aliases and databases ('default' is required)
		*  @param {String} [config.dbHost='localhost'] The name of the database host
		*  @param {int} [config.level=DeploymentStatus::LIVE] The deployment level of the site, see DeploymentStatus class for more info
		*  @param {Array|String} [config.domain='*'] Single domain or collection of domains acceptable for this deployment to run
		*  @param {Boolean} [config.debug=false] The debug mode
		*  @param {Boolean} [config.cacheEnabled=false] If the cache is enabled
		*  @param {Boolean} [config.compress=false] If the site should be compressed with gzip
		*  @param {Boolean} [config.minify=false] If the output page should be minified
		*  @param {String} [config.cacheDirectory=null] The directory for storing file cache if Memcache isn't available
		*  @param {String} [callerPath=null] The path to the root script of the site, useful if we
		*     are inheriting this class.
		*/
		public function __construct($config='config.php', $callerPath=null)
		{
			parent::__construct();

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

			// Save the configuration
			$this->_config = $config;

			// Handle any errors before Flight begins
			$this->handleErrors($this->get('flight.handle_errors'));

			// Error handler method
			$this->map('error', [$this, 'fatalError']);

			// Register required components
			$this->register('parser', 'Canteen\Parser\Parser');
			$this->register('settings', 'Canteen\Utilities\SettingsManager');
			$this->register('formFactory', 'Canteen\Forms\FormFactory');
			$this->register('gateway', 'Canteen\Server\Gateway');
			$this->register('user', 'Canteen\Authorization\Authorization');

			$this->map('registerService', ['Canteen\Services\Service','register']);

			// Load the Canteen templates
			$this->parser->addManifest(__DIR__.'/Templates/templates.json');

			// Register is the caller path, internal path 
			$this->settings->addSetting('callerPath', $callerPath);

			// Setup to run before the site renders
			$this->before('start', [$this, 'setup']);

			// Handle the gateway
			$this->route('/gateway/@call:*', [$this->gateway, 'handle']);

			// Initialize the logger
			if (class_exists('Canteen\Logger\Logger'))
			{
				Logger::init();
			}
		}

		/**
		*  Set the configuration setting
		*  @method addSetting
		*  @param {String} name The name of the item to set
		*  @param {String} value The value to set
		*  @param {Boolean} [access=0] The variable access
		*  @return {SettingsManager} The manager for chaining
		*/
		public function addSetting($name, $value, $access=0)
		{
			return $this->settings->addSetting($name, $value, $access);
		}

		/**
		*  Set the configuration setting
		*  @method addSettings
		*  @param {Dictionary} settings The collection of settings
		*  @param {Boolean} [access=0] The variable access
		*  @return {SettingsManager} The manager for chaining
		*/
		public function addSettings(array $settings, $access=0)
		{
			return $this->settings->addSettings($settings, $access);
		}

		/**
		*  Create a fatalError
		*  @method fatalError
		*  @param {Exception} e The caught exception
		*/
		public function fatalError(Exception $e)
		{
			$fatalError = ($e instanceof CanteenError) ? 
				$e->getResult():
				CanteenError::convertToResult($e);

			$debug = ifconstor('DEBUG', true);
			$async = ifconstor('ASYNC_REQUEST', false);
			
			$data = [
				'type' => 'fatalError',
				'debug' => $debug
			];
			
			$data = array_merge($fatalError, $data);
			
			if (!$debug) 
			{
				unset($data['stackTrace']);
				unset($data['file']);
			}

			if ($async)
			{
				echo json_encode($data);
			}
			else
			{
				if ($debug)
				{
					$data['stackTrace'] = new SimpleList($data['stackTrace'], null, 'ol');
				}
				$result = $this->parser->template('FatalError', $data);
				$this->parser->removeEmpties($result);

				$debugger = '';
			
				// The profiler
				if ($this->profiler)
				{
					$debugger .= $this->profiler->render();
				}
				// The logger
				if ($debug && class_exists('Canteen\Logger\Logger'))
				{
					$debugger .= Logger::instance()->render();
				}
				// If there are any debug trace or profiler
				if ($debugger)
				{
					$result = str_replace('</body>', $debugger . '</body>', $result);
				}
				echo $result;
			}
			return;
		}

		/**
		*  Before the page starts rendering, we should do some checking
		*  @method setup
		*/
		public function setup()
		{
			// Check for the version of PHP required to do the autoloading/namespacing
			if (version_compare(self::MIN_PHP_VERSION, PHP_VERSION) >= 0) 
			{
				throw new CanteenError(CanteenError::INSUFFICIENT_PHP, [PHP_VERSION, self::MIN_PHP_VERSION]);
			}

			$config = $this->_config;

			// Check that the settings exists
			if (is_string($config) && !file_exists($config))
			{
				$this->formFactory->startup('Canteen\Forms\SetupForm');
				die($this->parser->template('Setup',
					[
						'formFeedback' => $this->formFactory->getFeedback(),
						'configFile' => basename($config)
					]
				));
			}

			// Check the domain for the current deployment level 
			$status = new DeploymentStatus($config);

			// client, renderable, deletable, writeable
			$this->addSettings($status->settings)
				->access('fullPath', SETTING_RENDER)
				->access('local', SETTING_CLIENT | SETTING_RENDER)
				->access('host', SETTING_CLIENT | SETTING_RENDER)
				->access('basePath', SETTING_CLIENT | SETTING_RENDER)
				->access('baseUrl', SETTING_CLIENT)
				->access('uriRequest', SETTING_CLIENT)
				->access('queryString', SETTING_CLIENT | SETTING_RENDER)
				->access('debug', SETTING_CLIENT | SETTING_RENDER);

			$debug = $this->settings->debug;

			// Set the error reporting if we're set to debug
			error_reporting($debug ? E_ALL : 0);

			// Debug mode most be on in order to profile
			$profiler = false;

			if (class_exists('Canteen\Profiler\Profiler') 
				&& $debug && ifsetor($_GET['profiler']) == 'true')
			{
				$this->register('profiler', 'Canteen\Profiler\Profiler', [$this->parser]);
				$profiler = $this->profiler();
				
				// Assign the profiler to the parser
				$this->parser->setProfiler($profiler);
			}

			if ($profiler) $profiler->start('Canteen Setup');
			
			// Turn on or off the logger
			if (class_exists('Canteen\Logger\Logger'))
			{
				Logger::instance()->enabled = $debug;
			}
			
			// Setup the cache
			$this->register('cache', 'Canteen\Server\ServerCache', [
				$this->settings->cacheEnabled, 
				$this->settings->cacheDirectory
			]);

			// Create the non-database services
			$this->registerService('time', new TimeService);

			if ($profiler) $profiler->start('Database Connect');

			if ($this->settings->existsThrow('dbHost', 'dbUsername', 'dbPassword', 'dbName'))
			{
				$this->register('db', 'Canteen\Database\Database', [
					$this->settings->dbHost,
					$this->settings->dbUsername,
					$this->settings->dbPassword,
					$this->settings->dbName
				]);
					
				// Assign the server cache to the database
				$this->db()->setCache($this->cache);
				
				// Setup the database profiler calls
				if ($profiler)
				{
					$this->db->profilerStart = [$profiler, 'sqlStart'];
					$this->db->profilerStop = [$profiler, 'sqlEnd'];
				}
			}

			// If the database is connected
			if ($this->db && $this->db->isConnected())
			{
				$this->formFactory->startup('Canteen\Forms\InstallerForm');
				
				// Check for the installation process make it 
				// easier to start Canteen for the  first time
				$installed = ifsetor($_COOKIE['installed'], false);
				
				// Check to see if we're installed already
				if (!$installed)
				{
					if (!$this->db->tableExists('config'))
					{
						die($this->parser->template('Installer', 
							['formFeedback' => $this->formFactory->getFeedback()]
						));
					}
					else
					{
						// Table already exists, don't need to keep checking
						$_COOKIE['installed'] = true;
					}
				}
				
				$service = $this->registerService('config', new ConfigService);
				
				// Add the configuration db assets
				// Give all settings global render access and changability
				$service->registerSettings();

				// Add the main site template
				$this->parser->addTemplate(
					self::MAIN_TEMPLATE, 
					$this->settings->callerPath . $this->settings->templatePath
				);
				
				// Process database changes here
				$this->formFactory->startup('Canteen\Forms\DatabaseForm');
				
				// Check for database updates
				$this->isDatabaseUpdated('dbVersion', self::DB_VERSION, __DIR__.'/Upgrades/');
				
				// Create the services that require the database
				$this->registerService('page', new PageService);
				$this->registerService('user', new UserService);
			}
			
			if ($profiler) $profiler->end('Database Connect');
			
			if ($profiler) $profiler->start('Authorization');

			// Create the new user
			$this->user();

			// Add to the manager and allow render access for loggedIn and user name
			$this->settings->addSettings($this->user->settings)
				->access('loggedIn', SETTING_RENDER)
				->access('userFullname', SETTING_RENDER);

			if ($profiler) $profiler->end('Authorization');

			// Set the globals
			$this->_controllers = [];
			/*
			$this->addController('admin', 'Canteen\Controllers\AdminController');
			$this->addController('admin/users', 'Canteen\Controllers\AdminUserController');
			$this->addController('admin/pages', 'Canteen\Controllers\AdminPageController');
			$this->addController('admin/password', 'Canteen\Controllers\AdminPasswordController');
			$this->addController('admin/config', 'Canteen\Controllers\AdminConfigController');
			$this->addController('forgot-password', 'Canteen\Controllers\ForgotPasswordController');
			*/

			if ($debug)
			{
				// URL clear for the cache
				if (ifsetor($_GET['flush']) == 'all')
				{
					$this->cache->flush();
				}
				else if (ifsetor($_GET['flush']) == 'database')
				{
					if ($this->db) $this->db->flush();
				}
			}
			
			if ($profiler) $profiler->end('Canteen Setup');
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
				$version = $this->settings->$variableName;
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
				echo $this->parser->template(
					'UpgradeDatabase', 
					[
						'targetVersion' => $targetVersion,
						'version' => $version,
		 				'updatesFolder' => $updatesFolder,
						'variableName' => $variableName,
						'formFeedback' => $this->formFactory->getFeedback()
					]
				);
				exit;
			}
		}
		
		/**
		*  Get the current page markup, echoes on the page
		*  @method render
		*  @return {String} If capture is true, return the page render as a string
		*/
		public function render()
		{
			$this->start();
		}

		/**
		*  Private getters
		*/
		public function __get($name)
		{
			switch($name)
			{
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
				case 'parser' :

				/**
				*  The form factory handles the processing of form POST requests 
				*  @property {FormFactory} formFactory
				*  @readOnly
				*/
				case 'formFactory' :

				/**
				*  The instance of the database for fetching data using SQL
				*  @property {Database} db
				*  @readOnly
				*/		
				case 'db' :

				/** 
				*  The instance of the server cache 
				*  @property {ServerCache} cache
				*  @readOnly
				*/
				case 'cache' :

				/** 
				*  The user authorization object, deals with login and session
				*  @property {Authorization} user
				*  @readOnly
				*/
				case 'user' :

				/** 
				*  The site configuration an associative array in key => value pairs 
				*  @property {SettingsManager} settings
				*  @readOnly
				*/
				case 'settings' :
				
				/** 
				*  The Gateway is used for client connection to services or other data
				*  @property {Gateway} gateway
				*  @readOnly
				*/
				case 'gateway' :

				/** 
				*  The Profiler is use for debugging performance issues, SQL speed
				*  @property {Profiler} profiler
				*  @readOnly
				*/
				case 'profiler' : 

					return $this->$name();
			}
		}
		
		/**
		*  Check that a version of Canteen is required to run
		*  @method requiresVersion
		*  @param {String} required The least required version to run
		*/
		public function requiresVersion($required)
		{
			if (version_compare(self::VERSION, $required) < 0)
			{
				$this->fatalError(new CanteenError(
					CanteenError::INSUFFICIENT_VERSION, 
					[self::VERSION, $required]
				));
			}
		}
	}
}