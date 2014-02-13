<?php

/**
*  @module Canteen\PageBuilder
*/
namespace Canteen\PageBuilder
{
	use Canteen\Site;
	use Canteen\Authorization\Privilege;
	use Canteen\Errors\CanteenError;
	use Canteen\ServiceBrowser\ServiceBrowser;
	use Canteen\Profiler\Profiler;
	use Canteen\Logger\Logger;
	use Canteen\Services\Objects\Page;
	use Canteen\Utilities\CanteenBase;
	use Canteen\Utilities\StringUtils;
	use Canteen\Server\JSONServer;
	use Canteen\Services\Service;
	
	/**
	*  Responsible for building the pages and handling page requests.
	*  Located in the namespace __Canteen\PageBuilder__.
	*  @class PageBuilder
	*  @extends CanteenBase
	*/
	class PageBuilder extends CanteenBase
	{
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
		public function __construct()
		{			
			if ($this->profiler) $this->profiler->start('Build Page');
			
			// Check to see if this is a gateway request
			$this->_isGateway = strpos($this->settings->uriRequest, $this->site->gateway->uri) === 0;
			
			// Check to see if this is an ajax request
			define('ASYNC_REQUEST', ifsetor($_POST['async']) == 'true' || $this->_isGateway);
						
			// Add some render only properties
			$this->settings->addSettings(
				[
					'year' => date('Y'),
					'formSession' => StringUtils::generateRandomString(16),
					'logoutUri' => $this->site->logoutUri
				], 
				SETTING_RENDER
			)
			->addSetting('version', Site::VERSION, SETTING_CLIENT)
			->addSetting('gatewayPath', $this->settings->basePath . $this->site->gateway->uri, SETTING_CLIENT);
			
			// Check for the compression setting
			if ($this->settings->compress && extension_loaded('zlib')) 
			{
				ini_set("output_buffering", "Off");
				ini_set("zlib.output_compression", "Off");
				
				ob_start('ob_gzhandler');
			}
			
			// See if we should minify the output string
			// and strip all whitespace
			if ($this->settings->minify)
			{
				ob_start(array('Canteen\Utilities\StringUtils', 'minify'));
			}
			
			// Get the collection of all the pages
			$this->_pages = $this->service('page')->getPages();
		}
		
		/**
		*  Handle the current page request
		*  @method handle
		*  @return {String} Output stream
		*/
		public function handle()
		{
			$profiler = $this->profiler;
			$uriRequest = $this->settings->uriRequest;

			// Grab the default index page
			$this->_indexPage = $this->getPageByUri($this->settings->siteIndex);
			
			// There's no index page
			if (!$this->_indexPage)
			{
				throw new CanteenError(CanteenError::INVALID_INDEX, $this->settings->siteIndex);
			}
			
			// If we're processing a form
			if (isset($_POST['form'])) 
			{
				if ($profiler) $profiler->start('Form Process');
				
				// We save the result incase this is an ajax request
				$result = $this->site->formFactory->process($_POST['form'], ASYNC_REQUEST);
				
				if ($profiler) $profiler->end('Form Process');
				
				// To be save clear both render and data contexts after
				// a form is processed
				$this->flush();
				
				if (ASYNC_REQUEST)
				{
					if ($profiler) $profiler->end('Build Page');
					return $result;
				}
				else
				{
					$this->settings->addSetting('formFeedback', $this->site->formFactory->getFeedback(), SETTING_RENDER);
				}
			}
			
			// Log out the current user if request
			// redirects home
			if ($uriRequest === $this->site->logoutUri)
			{
				$this->flush();
				$this->user->logout();
				return redirect();
			}	
							
			// Check to see if we should display the browser
			// Setup the server and point to services directory
			// Only local deployments or administrators can use the 
			// service browser
			if (($this->settings->local || USER_PRIVILEGE == Privilege::ADMINISTRATOR) 
				&& class_exists('Canteen\ServiceBrowser\ServiceBrowser')
				&& $this->settings->debug 
				&& strpos($this->settings->uriRequest, $this->site->browserUri) === 0)
			{
				$browser = new ServiceBrowser(
					Service::getAll(),
					$this->settings->basePath,
					$this->site->browserUri,
					$this->settings->uriRequest,
					$this->parser
				);
				$result = $browser->handle();
				if ($profiler) $profiler->end('Build Page');
				return $result;
			}
			// Setup the gateway
			else if ($this->_isGateway)
			{				
				$result = $this->site->gateway->handle($uriRequest);
				if ($profiler) $profiler->end('Build Page');
				return $result;
			}
			// Handle the current page request based on the current URI
			else
			{
				return $this->handlePage($uriRequest, ASYNC_REQUEST);
			}
		}
		
		/**
		*  Get the current build time 
		*  @method buildTime
		*  @private
		*  @param {Boolean} isAsync If the request is made asyncronously
		*  @return {String} The build time HTML comment
		*/
		private function buildTime($isAsync)
		{
			// Parse the build time
			if (!DEBUG || $isAsync) return;
			
			$seconds = round((microtime(true) - $this->site->startTime) * 1000, 4);
			return html('comment', 'Page built in ' . $seconds . 'ms');
		}
		
		/**
		*  Clear both the render and data page caches
		*  @method flush
		*  @private
		*/
		private function flush()
		{
			$this->cache->flushContext(self::RENDER_CONTEXT);
			$this->cache->flushContext(self::DATA_CONTEXT);
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
		*  @method getPage
		*  @private
		*  @param {int} id The page ID to search for
		*  @return {Page} The site page matching the ID
		*/		
		private function getPage($id)
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
			$profiler = $this->profiler;
			
			if ($profiler) $profiler->start('Add Page Content');
			$page->content = @file_get_contents($page->contentUrl);
			
			// If we have a controller for this page			
			if ($controllerName = $this->site->getController($page->uri))
			{
				if ($profiler) $profiler->start('Page Controller');
				$controller = new $controllerName();
				$controller->setPage($page);
				$controller->process();
				$page = $controller->getPage();
				$this->setPageTitle($page);
				
				// Page controllers cannot override the base rendering substitutions, like
				// loggedIn, debug, local, etc
				$substitutions = array_merge($controller->getData(), $this->settings->getRender());
				if ($profiler) $profiler->end('Page Controller');
			}
			else
			{
				$this->setPageTitle($page);				
				$substitutions = $this->settings->getRender();
			}
			
			if ($profiler) $profiler->start('Page Parse');
			$this->parse($page->content, $substitutions);
			if ($profiler) $profiler->end('Page Parse');
			
			if ($profiler) $profiler->end('Add Page Content');	
			
			return $page;
		}
		
		/**
		*  Construct the 'pageTitle' and 'fullTitle' render settings
		*  @method setPageTitle
		*  @private
		*  @param {Page} page The page
		*/
		private function setPageTitle(Page $page)
		{
			// Get the page title				
			$this->settings->addSetting('pageTitle', $page->title, SETTING_RENDER);
			
			// Assemble the full page title
			$stateTitle = '';
			
			if ($parent = $this->getPage($page->parentId))
			{
				$stateTitle = ($parent->id == $page->id) ? ' . ' : ' . '. $parent->title . ' . ';
			}
			$fullTitle = $page->title . $stateTitle . $this->settings->siteTitle;
			
			$page->fullTitle = $fullTitle;
			$this->settings->addSetting('fullTitle', $fullTitle, SETTING_RENDER);
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
			$profiler = $this->profiler;
			
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
				$page = $this->getPage($page->redirectId);
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
				$cache = $this->cache->read($key);
				if ($cache !== false) 
				{
					return $cache . $this->buildTime($isAsync);
				}
			}
			
			// Get the page content
			$this->addPageContent($page);
			
			// Do a simplier for asyncronous requests
			if ($isAsync)
			{
				$this->removeEmpties($page->content);
				
				// Fix the links
				if ($profiler) $profiler->start('Parse Fix Path');
				$this->parser->fixPath($page->content, $this->settings->basePath);
				if ($profiler) $profiler->end('Parse Fix Path');
				
				$data = json_encode($page);
			}
			// Normal page render using the template
			else
			{
				// Assemble all of the page contents
				$this->settings->addSettings(
					[
						'content' => $page->content,
						'description' => $page->description,
						'keywords' => $page->keywords,
						'pageUri' => $page->uri,
						'pageId' => $page->pageId,
						'settings' => $this->getSettings()
					],
					SETTING_RENDER
				);
				
				$profiler = $this->profiler;
				
				if ($profiler) $profiler->start('Template Render');
				
				// Get the main template from the path
				$data = $this->template(Site::MAIN_TEMPLATE, $this->settings->getRender());
				
				// Fix the links to use the base path
				if ($profiler) $profiler->start('Parse Fix Path');
				$this->parser->fixPath($data, $this->settings->basePath);
				if ($profiler) $profiler->end('Parse Fix Path');
				
				// Clean up any lingering tags
				$this->removeEmpties($data);
				
				if ($profiler) $profiler->end('Template Render');
			}
			
			// Cache, if available
			if ($page->cache)
			{
				$this->cache->save($key, $data, $context);
			}
			
			if ($profiler) $profiler->end('Build Page');
			
			if (!$isAsync)
			{
				$this->addLoggerProfiler($data);
			}
			return $data  . $this->buildTime($isAsync);
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
			if ($this->profiler)
			{
				$result .= $this->profiler->render();
			}
			if ($this->settings->debug && class_exists('Canteen\Logger\Logger'))
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
			// PHP >= 5.4 compatibility
			if (defined('JSON_UNESCAPED_SLASHES'))
			{
				$settings = json_encode(
					$this->settings->getClient(), 
					JSON_UNESCAPED_SLASHES
				);
			}
			else
			{
				// PHP < 5.4
				$settings = str_replace('\\/', '/', 
					json_encode(
						$this->settings->getClient()
					)
				);
			}			
			return $this->template('Settings', ['settings' => $settings]);
		}
	}
}