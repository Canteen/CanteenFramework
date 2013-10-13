<?php

/**
*  @module Canteen\Server
*/
namespace Canteen\Server
{
	use Canteen\Utilities\ArrayUtils;
	use Canteen\Utilities\JSONUtils;
	use Canteen\Utilities\StringUtils;
	use Canteen\Errors\CanteenError;
	
	class DeploymentStatus
	{
		/** 
		*  The deployment level for a live site.
		*  @property {int} LIVE
		*  @static
		*  @final
		*/
		const LIVE = 4;
		
		/** 
		*  The deployment level for a staging site, typically a place where
		*  a site lives before it's ready to go live. Changes infrequently.
		*  @property {int} STAGING
		*  @static
		*  @final
		*/
		const STAGING = 3;
		
		/** 
		*  The deployment level for a development site, typically development 
		*  sites change nightly or very frequently.
		*  @property {int} DEVELOPMENT
		*  @static
		*  @final
		*/
		const DEVELOPMENT = 2;
		
		/** 
		*  The deployment level for a local site which us usually only for local
		*  development and is not typically viewable anyone but the developer.
		*  @property {int} LOCAL
		*  @static
		*  @final
		*/
		const LOCAL = 1;
		
		/** 
		*  Save the collection of deployable configuration variables 
		*  @property {Dictionary} data
		*  @public
		*/
		public $data = array();
		
		/** 
		*  The default settings
		*  @property {Dictionary} defaultSettings
		*  @private
		*/
		private $defaultSettings = array(
			
			/** 
			*  The default deployment leve
			*  @property {int} defaultSettings.level
			*  @private
			*  @default DeployementStatus::LIVE
			*/
			'level' => DeploymentStatus::LIVE,
			
			/** 
			*  The default deployment database host name
			*  @property {Boolean} defaultSettings.dbHost
			*  @private
			*  @default 'localhost'
			*/
			'dbHost' => 'localhost',
			
			/** 
			*  The default deployment level
			*  @property {Boolean} defaultSettings.domain
			*  @private
			*  @default '*'
			*/
			'domain' => '*',
			
			/** 
			*  The default deployment setting if debug mode is enabled
			*  @property {Boolean} defaultSettings.debug
			*  @private
			*  @default false
			*/
			'debug' => false,
			
			/** 
			*  The default deployment setting if cache is enabled
			*  @property {Boolean} defaultSettings.cacheEnabled
			*  @private
			*  @default false
			*/
			'cacheEnabled' => false,
			
			/** 
			*  The default deployment setting if gzip compression is on
			*  @property {Boolean} defaultSettings.compress
			*  @private
			*  @default false
			*/
			'compress' => false,
			
			/** 
			*  The default deployment setting if cache is enabled
			*  @property {String} defaultSettings.minify
			*  @private
			*  @default false
			*/
			'minify' => false
		);
		
		/**
		*  Does a check on the server host to see what server
		*  settings we should be using. Located in the namespace __Canteen\Server__.
		*  
		*  @class DeploymentStatus
		*  @constructor
		*  @param {String|Array} settingsPath The path to the settings JSON file or an Array
		*  @param {String} [domain=null] The domain to specific where we're coming from (optional)
		*         checks the server constants if domain is not supplied
		*/
		public function __construct($settingsPath, $domain=null)
		{
			error_reporting(E_ALL);
			
			// Settings is either an external JSON file or an array 
			$settings = (is_string($settingsPath) && fnmatch('*.json', $settingsPath)) ? 
				JSONUtils::load($settingsPath):
				$settingsPath;
			
			// Check for null settings here
			if (empty($settings) || !isset($settings) || !is_array($settings) || !count($settings))
			{
				throw new CanteenError(CanteenError::SETTINGS_REQUIRED);
			}
			
			// If the settings is a single deployment
			// then we'll add it a collection of deployments
			if (ArrayUtils::isAssoc($settings))
			{
				$settings = array($settings);
			}
			
			// If no domain is specified then we'll use the current domain
			// to compare against
			if ($domain === null)
			{
				$domain = ifsetor($_SERVER['HTTP_X_FORWARDED_HOST'], $_SERVER['HTTP_HOST']);
			}
			
			$basePath = dirname($_SERVER['PHP_SELF']);
			if ($basePath != '/') $basePath .= '/';
			
			$uriRequest = $this->processURI($basePath);
			
			// Setup the data
			$this->data = array_merge(
				array(
					'domain' => $domain,
					'host' => 'http://'.$domain,
					'uriRequest' => $uriRequest,
					'basePath' => $basePath,
					'baseUrl' => 'http://'.$domain.$basePath,
					'fullPath' => $basePath . $uriRequest
				), 
				$this->data
			);
			
			// Loop through each of the settings levels
			foreach($settings as $deploy)
			{
				$deploy = array_merge($this->defaultSettings, $deploy);
				
				$ds = ifsetor($deploy['domains'], array());
				$d = ifsetor($deploy['domain']);
				$l = ifsetor($deploy['level']);
				
				if ($d) $ds[] = $d;
				
				// Make sure the array matches
				if (StringUtils::fnmatchInArray($domain, $ds))
				{
					// Set the level to be the level property or the index
					define('DEPLOYMENT_LEVEL', $l);
					
					unset($deploy['domain'], $deploy['domains']);
					
					// If we're local
					$deploy['local'] = DEPLOYMENT_LEVEL == self::LOCAL;
					$this->data = array_merge($this->data, $deploy);
					break;
				}
			}
			
			// Save each data as a constant
			foreach($this->data as $property=>$value)
			{
				$c = StringUtils::convertPropertyToConst($property);
				if (!defined($c)) define($c, $value);
			}
			
			// There was a problem with detecting the domain regex
			if (!defined('DEPLOYMENT_LEVEL')) error("Error: No deployment level was specified");
		}
		
		/**
		*  Process the site URI
		*  @method processURI
		*  @private
		*  @param {String} basePath The base path of the site, if any
		*/
		private function processURI($basePath) 
	    {			
	        if (isset($_SERVER['REQUEST_URI']))
	        {
				
				if (isset($_SERVER['HTTP_X_ORIGINAL_URL']))
				{
					$request = substr($_SERVER['HTTP_X_ORIGINAL_URL'], 
						strpos($_SERVER['HTTP_X_ORIGINAL_URL'], $basePath) + strlen($basePath));
				}
				else
				{
					$request = substr($_SERVER['REQUEST_URI'], strlen($basePath));
				}
				$query_pos = strpos($request, '?');
				$query = '';
				if ($query_pos > -1)
				{
					$query = substr($request, $query_pos);
					$request = substr($request, 0, $query_pos);
				}
				
				$this->data['queryString'] = $query;
	     		$uri = explode('/', $request);
	            return implode('/', array_filter($uri, function($var)
				{
					return ($var != '');
				}));
	        }
	        return '';
	    }	
	}
}