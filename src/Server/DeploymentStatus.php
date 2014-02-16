<?php

/**
*  @module Canteen\Server
*/
namespace Canteen\Server
{
	use Canteen\Utilities\ArrayUtils;
	use Canteen\Utilities\StringUtils;
	use Canteen\Errors\CanteenError;
	use Canteen\Utilities\CanteenBase;

	class DeploymentStatus extends CanteenBase
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
		*  The URL query string
		*  @property {String} queryString
		*  @private
		*/
		private $queryString;
		
		/** 
		*  The default settings
		*  @property {Dictionary} defaultSettings
		*  @private
		*/
		private $defaultSettings = [
			
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
			'minify' => false,
			
			
			/** 
			*  The location of the write-able cache directory if using cache
			*  and memcache isn't enabled.
			*  @property {String} defaultSettings.cacheDirectory
			*  @private
			*  @default null
			*/
			'cacheDirectory' => null
		];
		
		/**
		*  Does a check on the server host to see what server
		*  settings we should be using. Located in the namespace __Canteen\Server__.
		*  
		*  @class DeploymentStatus
		*  @constructor
		*  @param {String|Array} settingsPath The path to the settings PHP file or an Array
		*  @param {String} [domain=null] The domain to specific where we're coming from (optional)
		*		 checks the server constants if domain is not supplied
		*/
		public function __construct($settingsPath, $domain=null)
		{
			error_reporting(E_ALL);
			
			// Settings is either an external PHP file or an array 
			$deployments = (is_string($settingsPath) && fnmatch('*.php', $settingsPath)) ? 
				require $settingsPath:
				$settingsPath;
			
			// Check for null settings here
			if (empty($deployments) || !isset($deployments) || !is_array($deployments) || !count($deployments))
			{
				throw new CanteenError(CanteenError::SETTINGS_REQUIRED);
			}
			
			// If the settings is a single deployment
			// then we'll add it a collection of deployments
			if ($this->isAssoc($deployments))
			{
				$deployments = [$deployments];
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
			$settings = [
				'queryString' => $this->queryString,
				'domain' => $domain,
				'host' => '//'.$domain,
				'uriRequest' => $uriRequest,
				'basePath' => $basePath,
				'baseUrl' => '//'.$domain.$basePath,
				'fullPath' => $basePath . $uriRequest
			];
			
			// Loop through each of the settings levels
			foreach($deployments as $deploy)
			{
				$deploy = array_merge($this->defaultSettings, $deploy);
				$domains = ifsetor($deploy['domain']);

				$l = ifsetor($deploy['level']);
				
				if (is_string($domains)) $domains = [$domains];				
				
				// Make sure the array matches
				if (StringUtils::fnmatchInArray($domain, $domains))
				{
					// Set the level to be the level property or the index
					define('DEPLOYMENT_LEVEL', $l);
					
					// Set the domain to be the actual domain
					$deploy['domain'] = $domain;
					
					// If we're local
					$deploy['local'] = DEPLOYMENT_LEVEL == self::LOCAL;
					$settings = array_merge($settings, $deploy);
					break;
				}
			}

			// Define some global constants that we can use anywhere
			$globals = [
				'basePath', 
				'debug', 
				'queryString', 
				'local', 
				'host', 
				'domain'
			];
			
			if (!defined('DEPLOYMENT_LEVEL'))
			{
				throw new CanteenError(CanteenError::NO_SETTINGS, $domain);
			}
						
			foreach($globals as $property)
			{
				define(
					StringUtils::convertPropertyToConst($property),
					$settings[$property]
				);
			}

			$this->settings->addSettings($settings)
				->access('fullPath', SETTING_RENDER)
				->access('local', SETTING_CLIENT | SETTING_RENDER)
				->access('host', SETTING_CLIENT | SETTING_RENDER)
				->access('basePath', SETTING_CLIENT | SETTING_RENDER)
				->access('baseUrl', SETTING_CLIENT)
				->access('uriRequest', SETTING_CLIENT)
				->access('queryString', SETTING_CLIENT | SETTING_RENDER)
				->access('debug', SETTING_CLIENT | SETTING_RENDER);
		}
		
		/**
		*  Check to see if an array is associative
		*  @method isAssoc
		*  @private
		*  @param {Array} arr The array to check
		*  @return {Boolean} if the array is associative
		*/
		private function isAssoc($arr)
		{
			return array_keys($arr) !== range(0, count($arr) - 1);
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

				$this->queryString = $query;

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