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
	use Canteen\HTML5\HTML5;
	
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
		*  The dynamic page controllers with keys 'uri' and 'controller'
		*  @property {Array} _controllers
		*  @private
		*/
		private $_controllers;

		/**
		*  Build a page builder
		*/
		public function __construct()
		{
			// allow the use of the global html() function
			HTML5::useGlobal();

			$this->_controllers = [];

			// Setup some basic settings for all pages
			$this->settings->addSettings(
				[
					'year' => date('Y'),
					'formSession' => StringUtils::generateRandomString(16),
					'logoutUri' => 'logout'
				], 
				SETTING_RENDER
			)
			->addSetting('version', Site::VERSION, SETTING_CLIENT)
			->addSetting(
				'cacheBust', 
				'?'.($this->settings->local ? 'cb='.time() : 'v='.$this->site->version),
				SETTING_RENDER | SETTING_CLIENT
			);

			// Get the collection of all the pages
			$this->_pages = $this->service('page')->getPages();
			$this->_indexPage = $this->getPageByUri($this->settings->siteIndex);

			if (!$this->_indexPage)
			{
				throw new CanteenError(CanteenError::INVALID_INDEX, $this->settings->siteIndex);
			}

			// Handle page not found error
			$this->site->map('notFound', [$this, 'notFound']);

			// Handle the form requests
			$this->site->route('POST /*', [$this, 'formHandler']);

			// Handle the user logging out
			$this->site->route('/logout', [$this, 'logout']);

			// Create a router for each page
			foreach($this->_pages as $page)
			{
				$uri = str_replace('/', '[\/]', $page->uri);
				$route = $page->isDynamic ? $uri . '(/@dynamicUri:*)' : $uri;
				$this->site->route('/'.$route, [$this, 'handle']);
				if ($page == $this->_indexPage) 
				{
					$this->site->route('/', [$this, 'handle']);
				}
			}

			$this->addController('admin', 'Canteen\Controllers\AdminController');
			$this->addController('admin/users', 'Canteen\Controllers\AdminUserController');
			$this->addController('admin/pages', 'Canteen\Controllers\AdminPageController');
			$this->addController('admin/password', 'Canteen\Controllers\AdminPasswordController');
			$this->addController('admin/config', 'Canteen\Controllers\AdminConfigController');
			$this->addController('forgot-password', 'Canteen\Controllers\ForgotPasswordController');

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
		}

		/**
		*  Handler for the form submission
		*  @method formHandler
		*/
		public function formHandler()
		{
			// If we're processing a form
			if (isset($_POST['form'])) 
			{
				$this->profiler->start('Form Process');
				$async = $this->settings->asyncRequest;
				$result = $this->site->formFactory->process($_POST['form'], $async);
				$this->profiler->end('Form Process');
				
				// To be save clear both render and data contexts after
				// a form is processed
				$this->flush();
				
				// If the request isn't an ajax one, then store the form feedback
				if (!$async)
				{
					$this->settings->addSetting(
						'formFeedback', 
						$this->site->formFactory->getFeedback(), 
						SETTING_RENDER
					);
				}
				else
				{
					echo $result;
					exit;
				}
			}
			return true;
		}
		
		/**
		*  Route handler when a user decided to logout
		*  @method logout
		*  @return {String} The redirect output
		*/
		public function logout()
		{
			$this->flush();
			$this->user->logout();
			$this->redirect();
		}

		/**
		*  Handle 404 requests from the router
		*  @method notFound
		*/
		public function notFound()
		{
			$page = $this->getPageByUri('404');

			$this->addPageContent($page);

			http_response_code(404);

			if ($this->settings->asyncRequest)
			{
				$this->handlePost($page);
			}
			else
			{
				$this->handleGet($page);
			}
		}

		/**
		*  Handle both page and post requests
		*  @method handle
		*  @param {String} [dynamicUri] The URI stub for dynamic pages
		*  @return {Boolean} If we should proceed with other routes
		*/
		public function handle($dynamicUri=null)
		{
			$uri = $dynamicUri ? 
				str_replace('/'.$dynamicUri, '', $this->settings->uriRequest):
				$this->settings->uriRequest;

			// Use index page if the uri is null
			$page = $this->getPageByUri($uri ? $uri : $this->_indexPage->uri);
			$page->dynamicUri = $dynamicUri;

			$async = $this->settings->asyncRequest;
			
			// No page available
			if ($page->privilege > $this->settings->userPrivilege)
			{
				http_response_code(401);
				$page = $this->getPageByUri('401');
			}
			else if ($page->redirectId)
			{
				$page = $this->getPage($page->redirectId);
				$this->redirect($page->uri);
			}
			
			// If we are on the default page, redirect to index
			if ($uri == $this->_indexPage->uri) $this->redirect();
			
			// Check to see if the page is cache-able
			if ($cache = $this->readCache($page))
			{
				echo $cache . $this->buildTime();
				return false;
			}
			
			// Get the page content
			$this->addPageContent($page);

			if ($async)
			{
				$this->handlePost($page);
			}
			else
			{
				$this->handleGet($page);
			}
		}

		/**
		*  Handle ajax post requests
		*  @method handlePost
		*/
		private function handlePost(Page $page)
		{
			$this->removeEmpties($page->content);
			$data = json_encode($page);
			$this->saveCache($data);
			echo $data;

			$code = http_response_code();
			if ($code == 404 || $code != 401) exit;
		}

		/**
		*  Handle the current page request
		*  @method handleGet
		*  @param {String} [dyanmicUri] Optional for dynamic pages
		*  @return {String} Output stream
		*/
		private function handleGet(Page $page)
		{
			$this->profiler->start('Build Page');

			$this->settings->addSettings(
				[
					'content' => $page->content,
					'description' => $page->description,
					'keywords' => $page->keywords,
					'pageUri' => $page->uri,
					'pageId' => $page->pageId,
					'settings' => $this->getClientSettings()
				],
				SETTING_RENDER
			);
			
			// Get the main template from the path
			$data = $this->template(Site::MAIN_TEMPLATE, $this->settings->getRender());
			
			// Clean up any lingering tags
			$this->removeEmpties($data);
			$this->saveCache($data);
			$this->profiler->end('Build Page');

			echo $this->addLoggerProfiler($data) . $this->buildTime();

			$code = http_response_code();
			if ($code != 200 || $code != 300) exit;
		}
		
		/**
		*  Store a page user function call for dynamic pages
		*  @method addController
		*  @param {String|RegExp|Array} pageUri The page ID of the dynamic page (array for multiple items) or can be an regular expression
		*  @param {String} controllerClassName The user class to call
		*/
		public function addController($pageUri, $controllerClassName)
		{
			if (!class_exists($controllerClassName))
			{
				$this->error(new CanteenError(CanteenError::INVALID_CLASS, [$controllerClassName]));
			}
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
		*  Get a page controller by uri
		*  @method getController
		*  @param {String} pageUri The uri of the page
		*  @return {Controller} The controller matching the URI
		*/
		private function getController($pageUri)
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
		*  Get the current build time 
		*  @method buildTime
		*  @private
		*  @return {String} The build time HTML comment
		*/
		private function buildTime()
		{
			// Parse the build time
			if (!$this->settings->debug || $this->settings->asyncRequest) return;
			
			$seconds = round((microtime(true) - $this->site->startTime) * 1000, 4);
			return (string)html('comment', 'Page built in ' . $seconds . 'ms');
		}

		/**
		*  Save the current page cache
		*  @method saveCache 
		*  @param {String} data The current page data
		*/
		private function saveCache($data)
		{
			if ($this->_page && $this->_page->cache)
			{
				$context = $this->settings->asyncRequest ? 
					self::DATA_CONTEXT : self::RENDER_CONTEXT;

				$key = md5($context . '::' . $this->_page->uri.'/'.$this->_page->dynamicUri);
				$this->cache->save($key, $data, $context);
			}
		}

		/**
		*  Save the current page cache
		*  @method readCache 
		*  @return {String} Data for the current page
		*/
		private function readCache()
		{
			// Check to see if the page is cache-able
			if ($this->_page && $this->_page->cache)
			{
				$context = $this->settings->asyncRequest ? 
					self::DATA_CONTEXT : self::RENDER_CONTEXT;

				// Check for the cache to see if we have a page
				$key = md5($context . '::' . $this->_page->uri.'/'.$this->_page->dynamicUri);
				$cache = $this->cache->read($key);
				if ($cache !== false) 
				{
					return $cache;
				}
			}
			return false;
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
			foreach($this->_pages as $p)
			{
				if ($p->uri == $uri) return $p;
			}
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
			$this->profiler->start('Add Page Content');
			$page->content = @file_get_contents($page->contentUrl);
			
			// If we have a controller for this page			
			if ($controllerName = $this->getController($page->uri))
			{
				$this->profiler->start('Page Controller');
				$controller = new $controllerName();
				$controller->setPage($page);
				$controller->process();
				$page = $controller->getPage();
				$this->setPageTitle($page);
				
				// Page controllers cannot override the base rendering substitutions, like
				// loggedIn, debug, local, etc
				$substitutions = array_merge($controller->getData(), $this->settings->getRender());
				$this->profiler->end('Page Controller');
			}
			else
			{
				$this->setPageTitle($page);				
				$substitutions = $this->settings->getRender();
			}
			$this->profiler->start('Page Parse');
			$this->parse($page->content, $substitutions);
			$this->profiler->end('Page Parse');
			$this->profiler->end('Add Page Content');
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
			$result .= $this->profiler->render();

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
		*  @method getClientSettings
		*  @private
		*  @return {String} HTML Script tag containing all the Canteen and custom settings
		*/
		private function getClientSettings()
		{
			$settings = json_encode(
				$this->settings->getClient(), 
				JSON_UNESCAPED_SLASHES
			);
			return $this->template('Settings', ['settings' => $settings]);
		}
	}
}