<?php

/**
*  @module Canteen\Server
*/
namespace Canteen\Server
{
	use Canteen\Errors\CanteenError;
	use Canteen\Database\IDatabaseCache;
	use Canteen\Utilities\StringUtils;
	use \Memcache;
		
	/**
	*  A server cache to reduce server load
	*  this will try to use Memcache if available or fallback to a file-base approach.  
	*  Located in the namespace __Canteen\Server__.
	*  
	*  @class ServerCache
	*  @constructor
	*  @param {String} [cacheDirectory=null] The folder to store the file-based cache
	*  @param {String} [server='127.0.0.1'] The IP of the memcache server we're connecting to
	*  @param {String} [port='11211'] The port of the memcache server
	*  @param {int} [expiresDefault=604800] The default time before caches expire (defaults to 7 days)
	*/
	class ServerCache implements IDatabaseCache
	{		
		/** 
		*  Set the expiration time (default 7 days) 
		*  @property {int} expiresDefault
		*  @private
		*/
		private $expiresDefault = 0;
		
		/** 
		*  The memcache connection 
		*  @property {Memcache} _memcache
		*  @private
		*/
		private $_memcache = null;
		
		/** 
		*  If the cache is turned on or not 
		*  @property {Boolean} _enabled
		*  @private
		*/
		private $_enabled = false;
		
		/** 
		*  use the file system to cache files 
		*  @property {Boolean} _useFileSystem
		*  @private
		*/
		private $_useFileSystem = false;
		
		/** 
		*  The path to the cache directory 
		*  @property {String} _cacheDirectory
		*  @private
		*/
		private $_cacheDirectory = null;
		
		/**
		*  Destroy the instance and don't use after this, or re-init
		*  @method destroy
		*/
		public function destroy()
		{
			if ($this->_memcache)
			{
				$this->_memcache->close();
				$this->_memcache = null;
			}
			$this->_enabled = false;
		}
		
		/**
		*  If we should use memcache to create the server
		*/		
		public function __construct($enabled, $cacheDirectory=null, $server='127.0.0.1', $port='11211', $expiresDefault=604800)
		{
			$this->_enabled = true;
			
			// Save the cache directory
			$this->_cacheDirectory = StringUtils::requireSeparator($cacheDirectory);
			
			// See if the class is available
			if (!class_exists('\Memcache'))
			{
				$this->_enabled = false;
			}
			else
			{
				// Save the default expiration
				$this->expiresDefault = $expiresDefault;
				
				// Create a new memcache connection
				$this->_memcache = new Memcache;

				// Silently tries to connect, retrun boolean
				$this->_enabled = @$this->_memcache->connect($server, $port);

				// Clear if we don't have a connection
				if (!$this->_enabled)
				{
					$this->_memcache = null;
				}
			}
			
			// If there's no memcache, then fallback to using the file system
			// the cache directory needs to be available
			if ($enabled && $this->_memcache == null && $this->_cacheDirectory != null)
			{
				// Make sure the folder exists
				if (!file_exists($this->_cacheDirectory))
				{
					// Try to make the directory
					if (!@mkdir($this->_cacheDirectory))
					{
						throw new CanteenError(CanteenError::CACHE_FOLDER, $this->_cacheDirectory);
					}					
				}
				else if (!is_writable($this->_cacheDirectory))
				{
					if (!@chmod($this->_cacheDirectory, 0777))
					{
						throw new CanteenError(CanteenError::CACHE_FOLDER_WRITEABLE, $this->_cacheDirectory);
					}
				}
				else
				{
					// Make sure the cache exists and that it's writeable
					$this->_useFileSystem = is_dir($this->_cacheDirectory);
				}
			}
						
			// constant is set by the deployment settings
			$this->setEnabled($enabled);
		}
		
		/**
		*  Get if this cache is enabled
		*  @method getEnabled
		*  @return {Boolean} if the cache is enabled
		*/
		public function getEnabled()
		{
			return $this->_enabled;
		}
		
		/**
		*  If the cache is enabled
		*  @method setEnabld
		*  @param {Boolean} enabled If this cache should be enabled
		*/
		public function setEnabled($enabled)
		{
			$this->_enabled = $enabled && ($this->_memcache || $this->_useFileSystem);
		}
		
		/**
		*  Add a context, or group to the cache to make it easier to flush certain things at once
		*  @method addContext
		*  @param {String} context The name of the context
		*  @param {int} [expires=-1] How many seconds before this expires, defaults to expiresDefault
		*/
		public function addContext($context, $expires=-1)
		{
			if (!$this->_enabled) return false;
			
			return $this->save($context, [], null, $expires);
		}
		
		/**
		*  Save and item to the server
		*  @method save
		*  @param {String} key The key of the item
		*  @param {mixed} value The value of the item
		*  @param {String} [context=null] If we should compress the item (defaults to true)
		*  @param {int} [expires=-1] How many seconds before this expires, defaults to expiresDefault
		*  @return {Boolean} If successfully saved
		*/
		public function save($key, $value, $context=null, $expires=-1)
		{
			if (!$this->_enabled) return false;
			
			if ($context !== null)
			{
				$this->pushContext($context, $key);
			}
			
			$expires = $expires == -1 ? $this->expiresDefault : $expires;
			
			// Only serialize the arrays and objects
			if (is_array($value) || is_object($value))
			{
				$value = serialize($value);
			}
			
			return ($this->_useFileSystem) ? 
				(@file_put_contents($this->_cacheDirectory . $key, $value) !== false):
				$this->_memcache->set($key, $value, false, $expires);
		}
		
		/**
		*  Save a collection of the cached keys to make it easier to clear later on
		*  @method pushContext
		*  @param {String} context The name of the context
		*  @param {String} key The key to save
		*/
		private function pushContext($context, $key)
		{
			$result = $this->read($context);
			
			if (!is_array($result)) $result = [];
			
			// If it's not already here add
			if (!in_array($key, $result))
			{
				array_push($result, $key);
			}
			
			$this->save($context, $result);
		}
		
		/**
		*  Read an item back from the cache
		*  @method read
		*  @param {String} The key of the item
		*  @param {Boolean} [output=false] If the file should be output directly (better memory management)
		*  @return {mixed} Return false if we can't read it, or it doesn't exist
		*/
		public function read($key, $output=false)
		{
			if (!$this->_enabled) return false;
			
			if ($this->_useFileSystem)
			{
				if ($output && file_exists($this->_cacheDirectory . $key))
				{
					readfile($this->_cacheDirectory . $key);
					return true;
				}
				$value = @file_get_contents($this->_cacheDirectory . $key);
			}
			else
			{
				$value = $this->_memcache->get($key);
			}	
			
			if ($value === false) return false;
			
			if (!StringUtils::isSerialized($value, $result))
			{
				return $value;
			}
			return $result;
		}
		
		/**
		*  Remove a value by a key
		*  @method delete
		*  @param {String} key The key of the item to remove
		*  @return {Boolean} if successful, false on failure
		*/
		public function delete($key)
		{
			if (!$this->_enabled) return false;
			
			return $this->_useFileSystem ?
				@unlink($this->_cacheDirectory . $key) :
				$this->_memcache->delete($key);
		}
		
		/**
		*  Delete a context (which is a group of related keys)
		*  @method flushContext
		*  @param {String} context The name of the context
		*  @return {Boolean} if we successfully deleted the context
		*/
		public function flushContext($context)
		{
			if (!$this->_enabled) return false;
			
			$keys = $this->read($context);
			
			if ($keys !== false)
			{
				foreach($keys as $key)
				{
					$this->delete($key);
				}
				return $this->delete($context);
			}
			return false;
		}
		
		/**
		*  Remove all of the items from the server
		*  @method flush
		*  @return {Boolean} if successfully flushed
		*/
		public function flush()
		{
			if (!$this->_enabled) return false;
			
			return ($this->_useFileSystem) ?
				(bool)array_map('unlink', glob($this->_cacheDirectory . '*')):
				$this->_memcache->flush();
		}
	}
}