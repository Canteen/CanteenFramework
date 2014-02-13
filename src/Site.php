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

	// We need to initalize flight before we can extend Engine
	// kind of hacky but it's the best solution
	// because Flight doesn't support prs-* loading with Composer
	\Flight::init();
	
	class Site extends Engine
	{
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

		private $_settings;
		
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
		
		public function __construct($settings='config.php', $callerPath=null)
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

			$this->_settings = $settings;

			// Handle any errors before Flight begins
			$this->handleErrors($this->get('flight.handle_errors'));

			// Error handler method
			$this->map('error', [$this, 'fatalError']);

			// Register the parser class
			$this->register('parser', 'Canteen\Parser\Parser');

			// Setup the settings manager
			$this->register('settings', 'Canteen\Utilities\SettingsManager');

			// Load the Canteen templates
			$this->parser()->addManifest(__DIR__.'/Templates/templates.json');

			// Setup to run before the site renders
			$this->before('start', [$this, 'setup']);

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
			return $this->settings()->addSetting($name, $value, $access);
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
			return $this->settings()->addSettings($settings, $access);
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

			if ($debug)
			{
				$data['stackTrace'] = new SimpleList($data['stackTrace'], null, 'ol');
			}
			$result = $this->parser()->template('FatalError', $data);
			$this->parser()->removeEmpties($result);

			die($result);
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

			$settings = $this->_settings;

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
			$this->addSettings($status->settings)
				->access('fullPath', SETTING_RENDER)
				->access('local', SETTING_CLIENT | SETTING_RENDER)
				->access('host', SETTING_CLIENT | SETTING_RENDER)
				->access('basePath', SETTING_CLIENT | SETTING_RENDER)
				->access('baseUrl', SETTING_CLIENT)
				->access('uriRequest', SETTING_CLIENT)
				->access('queryString', SETTING_CLIENT | SETTING_RENDER)
				->access('debug', SETTING_CLIENT | SETTING_RENDER);

			$debug = $this->settings()->debug;

			// Set the error reporting if we're set to debug
			error_reporting($debug ? E_ALL : 0);
			
			// Turn on or off the logger
			if (class_exists('Canteen\Logger\Logger'))
			{
				Logger::instance()->enabled = $debug;
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
		*  Get the current page markup, echoes on the page
		*  @method render
		*  @param {Boolean} [capture=false] If we should return the page render (false) or echo (true)
		*  @return {String} If capture is true, return the page render as a string
		*/
		public function render($capture=false)
		{
			$this->start();
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