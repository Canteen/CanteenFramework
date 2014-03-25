<?php

/**
*  @module Canteen
*/
namespace Canteen
{
	use \Exception;
	use flight\Engine;
	use Canteen\Utilities\Plugin;
	use Canteen\Errors\CanteenError;
	use Canteen\Server\DeploymentStatus;
	use Canteen\Services\TimeService;
	use Canteen\Services\ConfigService;
	use Canteen\Services\PageService;
	use Canteen\Services\UserService;
	use Canteen\Controllers\ErrorController;
	use Canteen\Utilities\StringUtils;

	// Optional Dev use only
	use Canteen\Logger\Logger;
	use Canteen\ServiceBrowser\ServiceBrowser;

	// We need to initalize flight before we can extend Engine
	// kind of hacky but it's the best solution
	// because Flight doesn't support psr-0 or psr-4 loading with Composer
	\Flight::init();
	
	class Site extends Engine
	{
		/** 
		*  The current version of the Canteen site 
		*  @property {String} VERSION  
		*  @static
		*  @final
		*/
		const VERSION = '1.2.0';
		
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
		*  The starting time to keep track of buildtime
		*  @property {int} startTime
		*/
		public $startTime;

		/**
		*  The collection of external plugins
		*  @property {Array} _plugins
		*  @private
		*/
		private $_plugins = [];
		
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
		*  @param {String} [domain=null] The explicit domain to run the site on, default is auto-detect.
		*     are inheriting this class.
		*/
		public function __construct($config='config.php', $callerPath=null, $domain=null)
		{
			parent::__construct();
			
			// Save singleton
			self::$_instance = $this;

			// Microseconds of start
			$this->startTime = microtime(true);

			// Handle any errors before Flight begins
			$this->handleErrors($this->get('flight.handle_errors'));

			// Error handler method
			$this->map('error', [new ErrorController, 'display']);

			// Setup the parser
			$this->register('parser', 'Canteen\Parser\Parser');
			$this->set('_parser', 1);
			$this->parser->addManifest(__DIR__.'/Templates/templates.json');

			// The settings manager setup
			$this->register('settings', 'Canteen\Utilities\SettingsManager');
			$this->set('_settings', 1);

			// Setup the form factory interception
			$this->register('formFactory', 'Canteen\Forms\FormFactory');
			$this->set('_formFactory', 1);

			// Register the JSON gateway
			$this->register('gateway', 'Canteen\Server\Gateway');
			$this->set('_gateway', 1);
			$this->registerPlugin($this->gateway);

			// Register the user class
			$this->register('user', 'Canteen\Authorization\Authorization');
			$this->set('_user', 1);

			// Register the page builder
			$this->register('pageBuilder', 'Canteen\PageBuilder\PageBuilder');
			$this->set('_pageBuilder', 1);

			/**
			*  Register a service to the site
			*  @method registerService
			*  @param {String} name The name of the service
			*  @param {Service} service The service instance
			*/
			$this->map('registerService', ['Canteen\Services\Service','register']);

			// Check for the version of PHP required to do the autoloading/namespacing
			if (version_compare(self::MIN_PHP_VERSION, PHP_VERSION) >= 0) 
			{
				throw new CanteenError(CanteenError::INSUFFICIENT_PHP, [PHP_VERSION, self::MIN_PHP_VERSION]);
			}

			if ($callerPath === null)
			{
				$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
				$callerPath = dirname($bt[0]['file']).'/';
				unset($bt);
			}

			// Setup the path we were called from
			$this->addSetting('callerPath', $callerPath);

			// If this page request was made by ajax 
			$this->addSetting('asyncRequest', ifsetor($_POST['async']) == 'true', SETTING_WRITE);

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
			$status = new DeploymentStatus($config, $domain);
			
			// Get the debug status
			$debug = $this->settings->debug;

			// Set the error reporting if we're set to debug
			error_reporting($debug ? E_ALL : 0);

			$profiler = ($debug && class_exists('Canteen\Profiler\Profiler') 
				&& ifsetor($_GET['profiler']) == 'true') ?
					'Canteen\Profiler\Profiler' :
					'Canteen\Utilities\EmptyProfiler';

			$this->register('profiler', $profiler, [$this->parser]);
			$this->set('_profiler', 1);
			
			// Pass the profiler to the parser
			// to measure the page parsing time
			$this->parser->setProfiler($this->profiler);

			$this->profiler->start('Canteen Setup');

			// Initialize the logger
			if (class_exists('Canteen\Logger\Logger'))
			{
				Logger::init();
				Logger::instance()->enabled = $debug;
			}
		
			// Setup the cache
			$this->register('cache', 'Canteen\Server\ServerCache', [
				$this->settings->cacheEnabled, 
				$this->settings->cacheDirectory
			]);
			$this->set('_cache', 1);

			$this->registerService('time', new TimeService);
			$this->bootstrapDatabase();
			
			// Create the new user
			$this->profiler->start('Authorization');
			$this->user();
			$this->profiler->end('Authorization');

			if (class_exists('Canteen\ServiceBrowser\ServiceBrowser'))
			{
				$this->registerPlugin(new ServiceBrowser);
			}

			if ($debug)
			{
				// URL clear for the cache
				if (ifsetor($_GET['flush']) == 'all')
				{
					$this->cache->flush();
				}
				else if ($this->db && ifsetor($_GET['flush']) == 'database')
				{
					$this->db->flush();
				}
			}
			$this->profiler->end('Canteen Setup');
		}
		
		/**
		*  Bootstrap the database connection
		*  @method bootstrapDatabase
		*/
		private function bootstrapDatabase()
		{
			$this->profiler->start('Database Connect');

			if ($this->settings->existsThrow('dbHost', 'dbUsername', 'dbPassword', 'dbName'))
			{
				$this->register('db', 'Canteen\Database\Database', [
					$this->settings->dbHost,
					$this->settings->dbUsername,
					$this->settings->dbPassword,
					$this->settings->dbName
				]);
				$this->set('_db', 1);
					
				// Assign the server cache to the database
				$this->db->setCache($this->cache);
				
				// Setup the database profiler calls
				$this->db->profilerStart = [$this->profiler, 'sqlStart'];
				$this->db->profilerStop = [$this->profiler, 'sqlEnd'];
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
			$this->profiler->end('Database Connect');
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
		*  Register plugin to the site
		*  @method registerPlugin
		*  @param {Plugin} plugin The plugin to add
		*/
		public function registerPlugin(Plugin $plugin)
		{
			$this->_plugins[] = $plugin;
		}

		/**
		*  Get the collection of plugins
		*  @method getPlugins
		*  @return {Array} The collection of Plugin objects
		*/
		public function getPlugins()
		{
			return $this->_plugins;
		}
		
		/**
		*  Get the current page markup, echoes on the page
		*  @method render
		*  @return {String} If capture is true, return the page render as a string
		*/
		public function render()
		{
			$this->profiler->start('Activate Plugins');
			foreach($this->_plugins as $plugin)
			{
				$plugin->activate();
			}
			$this->profiler->end('Activate Plugins');

			$this->pageBuilder();
			$this->start();
		}

		/**
		*  Store a page user function call for dynamic pages
		*  @method addController
		*  @param {String|RegExp|Array} pageUri The page ID of the dynamic page (array for multiple items) or can be an regular expression
		*  @param {String} controllerClassName The user class to call
		*/
		public function addController($pageUri, $controllerClassName)
		{
			$this->pageBuilder->addController($pageUri, $controllerClassName);
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
		*  Private getters
		*/
		public function __get($name)
		{
			if (!$this->has('_'.$name))
			{
				return;
			}

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
				*  The Page Builder is design for handling the rendering and routing of site pages
				*  @property {PageBuilder} pageBuilder
				*  @readOnly
				*/
				case 'pageBuilder' :

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
		*  Redirect to another page on the local site. 
		*
		*	$this->site->redirect('about');
		*
		*  @method redirect
		*  @param {String} [uri=''] The URI stub to redirect to
		*  @return {String} If the request is made asynchronously, returns the json redirect object as a string
		*/
		public function redirect($uri='')
		{
			$query = $this->settings->queryString;
			$uri = $uri . ($query ? '/' . $query : '');
			if ($this->settings->asyncRequest)
			{
				echo json_encode(array('redirect' => $uri));
			}
			else
			{
				$host = $this->settings->host;
				$basePath = $this->settings->basePath;
				header('Location: '.  $host . $basePath . $uri);
			}
			exit;
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
				$this->error(new CanteenError(
					CanteenError::INSUFFICIENT_VERSION, 
					[self::VERSION, $required]
				));
			}
		}
	}
}