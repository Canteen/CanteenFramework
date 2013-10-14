<?php

/**
*  @module Canteen\Parser
*/
namespace Canteen\Parser
{
	use Canteen\Site;
	use Canteen\Authorization\Privilege;
	use Canteen\Errors\CanteenError;
	use Canteen\Services\ServiceBrowser;
	use Canteen\Profiler\Profiler;
	use Canteen\Logger\Logger;
	use Canteen\Services\Objects\Page;
	use Canteen\Utilities\CanteenBase;
	use Canteen\Utilities\StringUtils;
	use Canteen\Parser\Parser;
	use Canteen\Server\JSONServer;
	
	/**
	*  Responsible for building the pages and handling page requests.
	*  Located in the namespace __PageBuilder__.
	*  @class PageBuilder
	*  @extends CanteenBase
	*  @constructor
	*  @param {Dictionary} [customSettings=null] If there are any custom settings to display on the page
	*/
	class PageBuilder extends CanteenBase
	{
		/** 
		*  The data
		*  @property {Dictionary} _data
		*  @private
		*/
		private $_data;
		
		/** 
		*  The page for the index of the site
		*  @property {Page} _indexPage
		*  @private
		*/
		private $_indexPage;
		
		/** 
		*  The collection of all pages
		*  @property {Array} _pages
		*  @private
		*/
		private $_pages;
		
		/** 
		*  See if the current view is the gate way
		*  @property {Boolean} _isGateway 
		*  @private
		*/
		private $_isGateway;
		
		/** 
		*  The collection is a list of data keys to use for the global JS Canteen settings
		*  @property {Dictionary} _customSettings 
		*  @private
		*/
		private $_customSettings;
		
		/** 
		*  The cache context for page renders
		*  @property {String} RENDER_CONTEXT
		*  @static
		*  @final
		*/
		const RENDER_CONTEXT = 'Canteen_PageRender';
		
		/** 
		*  The cache context for the page JSON data
		*  @property {String} DATA_CONTEXT
		*  @static
		*  @final
		*/
		const DATA_CONTEXT = 'Canteen_PageData';
		
		/**
		*  Build a page builder
		*/
		public function __construct($customSettings=null)
		{
			if (PROFILER) Profiler::start('Build Page');
			
			// Check to see if this is a gateway request
			$this->_isGateway = strpos(URI_REQUEST, $this->site()->gatewayUri) === 0;
			
			// Save for when we generate the settings
			$this->_customSettings = $customSettings;
			
			// Check to see if this is an ajax request
			define('ASYNC_REQUEST', ifsetor($_POST['async']) == 'true' || $this->_isGateway);
			
			// Add page building specific properties
			$this->_data = array_merge(
				array(
					'year' => date('Y'),
					'version' => Site::VERSION,
					'formSession' => StringUtils::generateRandomString(16),
					'logoutUri' => $this->site()->logoutUri,
					'gatewayPath' => $this->data('basePath').$this->site()->gatewayUri
				),
				$this->data()
			);
			
			// Check for the compression setting
			if (COMPRESS && extension_loaded('zlib')) 
			{
				ini_set("output_buffering", "Off");
				ini_set("zlib.output_compression", "Off");
				
				ob_start('ob_gzhandler');
			}
			
			// See if we should minify the output string
			// and strip all whitespace
			if (MINIFY)
			{
				ob_start(array('Canteen\Utilities\StringUtils', 'minify'));
			}
			
			// Get the collection of all the pages
			$this->_pages = $this->service('pages')->getPages();
		}
		
		/**
		*  Handle the current page request
		*  @method handle
		*  @return {String} Output stream
		*/
		public function handle()
		{			
			// Grab the default index page
			$this->_indexPage = $this->getPageByUri($this->_data['siteIndex']);
			
			// There's no index page
			if (!$this->_indexPage)
			{
				throw new CanteenError(CanteenError::INVALID_INDEX, $this->_data['siteIndex']);
			}
			
			// If we're processing a form
			if (isset($_POST['form'])) 
	        {
				if (PROFILER) Profiler::start('Form Process');
				
				// We save the result incase this is an ajax request
				$result = $this->site()->getFormFactory()->process($_POST['form'], ASYNC_REQUEST);
				
				if (PROFILER) Profiler::end('Form Process');
				
				// To be save clear both render and data contexts after
				// a form is processed
				$this->flush();
				
				if (ASYNC_REQUEST)
				{
					if (PROFILER) Profiler::end('Build Page');
					return $result;
				}
				else
				{
					$this->_data['formFeedback'] = $this->site()->getFormFactory()->getFeedback();
				}
			}
			
			// Log out the current user if request
			// redirects home
			if (URI_REQUEST === $this->site()->logoutUri)
			{
				$this->flush();
				$this->user()->logout();
				return redirect();
			}	
							
			// Check to see if we should display the browser
			// Setup the server and point to services directory
			// Only local deployments or administrators can use the 
			// service browser
			if ((LOCAL || USER_PRIVILEGE == Privilege::ADMINISTRATOR) 
				&& DEBUG && strpos(URI_REQUEST, $this->site()->browserUri) === 0)
			{
				$browser = new ServiceBrowser();
				$result = $browser->handle();
				if (PROFILER) Profiler::end('Build Page');
				return $result;
			}
			// Setup the gateway
			else if ($this->_isGateway)
			{				
				$server = new JSONServer();
				$result = $server->handle();
				if (PROFILER) Profiler::end('Build Page');
				return $result;
			}
			// Handle the current page request based on the current URI
			else
			{
				return $this->handlePage(URI_REQUEST, ASYNC_REQUEST);
			}
		}
		
		/**
		*  Construct the site title
		*  @method getSiteTitle
		*  @private
		*  @param {Page} page The page
		*  @return {String} The page title string
		*/
		private function getSiteTitle(Page $page)
		{
			if (!$page) return '';
			
			$stateTitle = '';
			
			if ($parent = $this->getPageById($page->parentId))
			{
				$stateTitle = ($parent->id == $page->id) ? ' . ' : ' . '. $parent->title . ' . ';
			}
			return $page->title . $stateTitle . $this->_data['siteTitle'];
		}
		
		/**
		*  Clear both the render and data page caches
		*  @method flush
		*  @private
		*/
		private function flush()
		{
			$this->cache()->flushContext(self::RENDER_CONTEXT);
			$this->cache()->flushContext(self::DATA_CONTEXT);
		}
		
		/**
		*  Search a page by a uri
		*  @method getPageByUri
		*  @private
		*  @param {String} uri The page URI
		*  @return {Page} The page matching the URI
		*/		
		private function getPageByUri($uri)
		{
			$page = null;
			foreach($this->_pages as $p)
			{
				if ($p->uri == $uri)
				{
					$page = $p;
					break;
				}
				else if ($p->isDynamic && strpos($uri, $p->uri) === 0)
				{
					$p->dynamicUri = str_replace($p->uri . '/', '', $uri);
					$page = $p;
					break;
				}
			}
			return $page;
		}
		
		/**
		*  Search a page by a uri
		*  @method getPageById
		*  @private
		*  @param {int} id The page ID to search for
		*  @return {Page} The site page matching the ID
		*/		
		private function getPageById($id)
		{
			foreach($this->_pages as $p)
			{
				if ($p->id == $id)
				{
					return $p;
				}
			}
			return null;
		}
		
		/**
		*  Generate the page content
		*  @method addPageContent
		*  @private
		*  @param {Page} page The page object
		*  @return {Page} The updated page object
		*/
		private function addPageContent(&$page)	
		{
			if (PROFILER) Profiler::start('Add Page Content');
			$page->content = @file_get_contents($page->contentUrl);
			if ($controllerName = $this->site()->getController($page->uri))
			{
				if (PROFILER) Profiler::start('Page Controller');
				$controller = new $controllerName($page, $page->dynamicUri);
				$page = $controller->getPage();
				$this->_data = array_merge(
					$controller->getData(),
					$this->_data
				);
				if (PROFILER) Profiler::end('Page Controller');
			}
			$this->_data['pageTitle'] = $page->title;
			$this->_data['fullTitle'] = $page->fullTitle = $this->getSiteTitle($page);
			Parser::parse($page->content, $this->_data);
			if (PROFILER) Profiler::end('Add Page Content');
			return $page;
		}
		
		/**
		*  Render current page
		*  @method handlePage
		*  @private
		*  @param {String} uri The current uri for the page request
		*  @param {Boolean} isAsync If the request is to be made asyncronously
		*  @return {String} Page rendering
		*/
		private function handlePage($uri, $isAsync)
		{			
			// Use index page if the uri is null
			$page = $this->getPageByUri($uri ? $uri : $this->_indexPage->uri);
			
			// Check for the cache
			$context = $isAsync ? self::DATA_CONTEXT : self::RENDER_CONTEXT;
			
			// No page available
			if (!$page)
			{
				$page = $this->getPageByUri('404');
			} 
			else if ($page->privilege > USER_PRIVILEGE)
			{
				// Don't change the header for asyncronous requests
				if (!$isAsync) header('HTTP/1.1 401 Unauthorized');
				$page = $this->getPageByUri('401');
			}
			else if ($page->redirectId)
			{
				$page = $this->getPageById($page->redirectId);
				redirect($page->uri);
				return;
			}
			
			// If this is a 404, add the header
			if ($page->uri == '404' && !$isAsync)
			{
				header("HTTP/1.0 404 Not Found");
			}
			
			// If we are on the default page, redirect to index
		    if ($uri == $this->_indexPage->uri) return redirect();
			
			// Check to see if the page is cache-able
			if ($page->cache)
			{
				// Check for the cache to see if we have a page
				$key = md5($context . '::' . $uri);
				$cache = $this->cache()->read($key);
				if ($cache !== false) 
				{
					return $cache;
				}
			}
			
			// Get the page content
			$this->addPageContent($page);
			
			// Do a simplier for asyncronous requests
			if ($isAsync)
			{
				Parser::removeEmpties($page->content);
				$data = json_encode($page);
			}
			// Normal page render using the template
			else
			{
				// Assemble all of the page contents
				$this->_data = array_merge(
					array(
						'content' => $page->content,
						'description' => $page->description,
						'keywords' => $page->keywords,
						'pageUri' => $page->uri,
						'pageId' => $page->pageId,
						'settings' => $this->getSettings()
					),
					$this->_data
				);
				
				if (PROFILER) Profiler::start('Template Render');
				
				// Get the main template from the path
				$data = Parser::getTemplate(MAIN_TEMPLATE, $this->_data);
				
				// Clean up
				Parser::removeEmpties($data);
				
				if (PROFILER) Profiler::end('Template Render');
			}
			
			// Cache, if available
			if ($page->cache)
			{
				$this->cache()->save($key, $data, $context);
			}
			
			if (PROFILER) Profiler::end('Build Page');
			
			if (!$isAsync)
			{
				$this->addLoggerProfiler($data);
			}
			return $data;
		}
		
		/**
		*  Add the logger and profiler to the output
		*  @method addLoggerProfiler
		*  @private
		*  @param {String} contents The HTML page contents
		*  @return {String} The updated page contents
		*/
		private function addLoggerProfiler(&$contents)
		{
			$result = '';
			
			// The profiler
			if (PROFILER)
			{
				$result .= Profiler::render();
			}
			if (DEBUG)
			{
				$result .= Logger::instance()->render();
			}
			
			if ($result)
			{
				$contents = str_replace('</body>', $result . '</body>', $contents);
			}
			return $contents;	
		}
		
		/**
		*  Generate a list of settings to use
		*  @method getSettings
		*  @private
		*  @return {String} HTML Script tag containing all the Canteen and custom settings
		*/
		private function getSettings()
		{			
			$defaultSettings = array(
				'version',
				'local',
				'debug',
				'host',
				'basePath',
				'baseUrl',
				'siteIndex',
				'queryString',
				'uriRequest',
				'gatewayPath'
			);
			
			$settings = array();
			
			// Add the custom settings
			if (is_array($this->_customSettings))
				$defaultSettings = array_merge($this->_customSettings, $defaultSettings);
			
			foreach($defaultSettings as $key)
			{
				// Make sure the data is set
				if (!isset($this->_data[$key])) continue;
				
				$value = $this->_data[$key];
				if (is_bool($value))
				{
					$value = $value ? 'true' : 'false';
				}
				else if (is_string($value))
				{
					$value = "'$value'";
				}
				$settings[] = "$key:$value";
			}
			
			return Parser::getTemplate(
				'Settings', 
				array(
					'settings' => implode(", ", $settings)
				)
			);
		}
	}
}