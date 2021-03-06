<?php

/**
*  @module Canteen\Forms
*/
namespace Canteen\Forms
{
	use Canteen\Utilities\Validate;
	use Canteen\Server\DeploymentStatus;
	
	/**
	*  Handle the necessary database and deployment setup.
	*  Located in the namespace __Canteen\Forms__.
	*  @class SetupForm
	*  @extends Form
	*/
	class SetupForm extends Form
	{
		public function __construct()
		{			
			$dbHost = $this->verify(ifsetor($_POST['dbHost'], 'localhost'), Validate::URI);
			$dbUsername = $this->verify(ifsetor($_POST['dbUsername']), Validate::FULL_TEXT);
			$dbPassword = $this->verify(ifsetor($_POST['dbPassword']), Validate::FULL_TEXT);
			$dbName = $this->createCollection('dbName');
			
			$debug = isset($_POST['debug']);
			$cacheEnabled = isset($_POST['cacheEnabled']);
			$minify = isset($_POST['minify']);
			$compress = isset($_POST['compress']);
			$cacheDirectory = $this->verify(ifsetor($_POST['cacheDirectory']), Validate::FULL_TEXT);
			$level = $this->verify(ifsetor($_POST['level'], DeploymentStatus::LIVE));
			$domain = $this->createCollection('domain', '*');
			
			$configFile = $this->verify(ifsetor($_POST['configFile']), Validate::FILE_NAME);
			
			if (!$configFile)
				$this->error('Settings file is required');
			
			if (!$dbUsername)
				$this->error('Database username is required');
			
			if (!$dbPassword)
				$this->error('Database password is required');
			
			if (!count($dbName))
				$this->error('Database name is required');

			if (!$this->ifError)
			{
				$configPath = $this->settings->callerPath . $configFile;
				$htaccessPath = $this->settings->callerPath . '.htaccess';
				
				$config = $this->template('Config', 
					[
						'properties' => var_export([
							'dbHost' => $dbHost,
							'dbUsername' => $dbUsername,
							'dbPassword' => $dbPassword,
							'dbName' => $this->arrayFilter($dbName),
							'level' => $level,
							'domain' => $this->arrayFilter($domain),
							'debug' => $debug,
							'minify' => $minify,
							'cacheEnabled' => $cacheEnabled,
							'cacheDirectory' => $cacheDirectory,
							'compress' => $compress											
						], true)
					]
				);
				
				$basePath = dirname($_SERVER['PHP_SELF']);
				if ($basePath != '/') $basePath .= '/';
				
				$htaccess = $this->template('Htaccess', [
					'basePath' => $basePath
				]);
				
				if (!is_writable($this->settings->callerPath))
				{
					$this->error("Please manually save the file.");
					
					die($this->template('SetupManual', [
						'config' => $config,
						'configFile' => $configFile,
						'htaccess' => $htaccess
					]));
				}
				else
				{
					$isConfig = @file_put_contents($configPath, htmlspecialchars_decode($config));
					$isHtaccess = @file_put_contents($htaccessPath, $htaccess);
					
					if ($isConfig === false)
					{
						$this->error('There was a problem saving the file '.$configPath);
					}
					else if ($isHtaccess === false)
					{
						$this->error('There was a problem saving the file '.$htaccessPath);
					}
					else
					{
						// Need to reload the site so we don't show the Setup form again
						$this->redirect();
					}
				}
			}
		}
		
		/**
		*  If there's only one item in the array, return as a string, not an arra 
		*  @method arrayFilter
		*  @private
		*  @param {Array} arr The array to pass
		*  @return {Array|String} The result
		*/
		private function arrayFilter($arr)
		{
			if (count($arr) == 1)
			{
				return $arr[0];
			}
			return $arr;
		}
		
		/**
		*  Create the collection based on comma-separated, POST variable
		*  @method createCollection
		*  @private
		*  @param {String} str The name of the POST variable
		*  @param {String} [default=''] The default value if nothing is set
		*  @return {Array} The collection of items
		*/
		private function createCollection($str, $default='')
		{
			$result = $this->verify(ifsetor($_POST[$str], $default), Validate::FULL_TEXT);
			$result = explode(',', $result);
			$result = array_filter($result);
			return array_map('trim', $result);
		}
	}
}