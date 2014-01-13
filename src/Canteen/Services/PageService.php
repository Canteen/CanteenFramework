<?php

/**
*  @module Canteen\Services
*/
namespace Canteen\Services
{	
	use Canteen\Authorization\Privilege;
	use Canteen\Utilities\Validate;
	
	/**
	*  Service for interacting with site pages in the database.  Located in the namespace __Canteen\Services__.
	*  
	*  @class PageService
	*  @extends SimpleObjectService
	*/
	class PageService extends SimpleObjectService
	{	
		/**
		*  Create the service
		*/
		public function __construct()
		{
			parent::__construct(
				'page',
				'Canteen\Services\Objects\Page',
				'pages',
				array(
					$this->field('page_id', Validate::NUMERIC, 'id')
						->setDefault(),
					$this->field('uri', Validate::URI)
						->setIndex(),
					$this->field('title', Validate::FULL_TEXT),
					$this->field('description'),
					$this->field('keywords', Validate::FULL_TEXT),
					$this->field('redirect_id', Validate::NUMERIC),
					$this->field('parent_id', Validate::NUMERIC)
						->setIndex()
						->setSelect('IF(`parent_id` is NULL || `parent_id`=0, `page_id`, `parent_id`) as `parentId`'),
					$this->field('is_dynamic', Validate::BOOLEAN),
					$this->field('privilege', Validate::NUMERIC),
					$this->field('cache', Validate::BOOLEAN)
				)
			);

			$form = array(
				Privilege::ADMINISTRATOR, 
				'Canteen\Forms\PageUpdate'
			);

			$this->restrict(
				array(
					'getPage' => array(
						'Canteen\Parser\PageBuilder', 
						'Canteen\Controllers\AdminPagesController'
					),
					'setup' => 'Canteen\Forms\Installer',
					'getPageByUri' => 'Canteen\Forms\ConfigUpdate',
					'getPagesByParentId' => 'Canteen\Controllers\AdminController',
					'getPages' => array(
						'Canteen\Parser\PageBuilder', 
						'Canteen\Controllers\AdminPagesController'
					),
					'removePage' => $form,
					'addPage' => $form,
					'updatePage' => $form,
					'getPages' => Privilege::ANONYMOUS
				)
			)
			->setProperties(
				'CONCAT(`uri`,\'.html\') as `contentUrl`',
				"REPLACE(`uri`, '/', '-') as `pageId`"
			)
			->setPrepends(
				'contentUrl', 
				$this->settings->exists('contentPath') ? $this->settings->contentPath : ''
			);
		}
		
		/**
		*  Install the pages table into the database
		*  @method setup
		*/
		public function setup()
		{		
			$this->access();
			
			if (!$this->db->tableExists($this->table))
			{
				$sql = "CREATE TABLE IF NOT EXISTS `pages` (
				  `page_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
				  `uri` varchar(64) NOT NULL,
				  `title` varchar(64) NOT NULL,
				  `redirect_id` int(10) unsigned DEFAULT NULL,
				  `parent_id` int(10) unsigned DEFAULT NULL,
				  `is_dynamic` int(1) unsigned NOT NULL DEFAULT '0',
				  `keywords` text NOT NULL,
				  `description` text NOT NULL,
				  `privilege` int(1) unsigned NOT NULL DEFAULT '0',
				  `cache` int(1) unsigned NOT NULL DEFAULT '0',
				  PRIMARY KEY (`page_id`),
				  UNIQUE KEY `uri` (`uri`)
				) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;";
				
				$result = (bool)$this->db->execute($sql);
				
				if ($result)
				{
					return $this->db->insert($this->table)
						->fields('page_id', 'uri', 'title', 'redirect_id', 
							'parent_id', 'is_dynamic', 'keywords', 'description', 'privilege', 'cache')
						->values(1, '403', 'Forbidden', NULL, NULL, 0, '', '', 0, 1)
						->values(2, '401', 'Access Denied', NULL, NULL, 0, '', '', 0, 1)
						->values(3, '404', 'Page Not Found', NULL, NULL, 0, '', '', 0, 1)
						->values(4, '500', 'Internal Server Error', NULL, NULL, 0, '', '', 0, 1)
						->values(5, 'home', 'Home', NULL, NULL, 0, '', '', 0, 1)
						->values(6, 'admin', 'Admin', NULL, NULL, 0, '', '', 1, 0)
						->values(7, 'admin/users', 'Users', NULL, 6, 0, '', '', 4, 0)
						->values(8, 'admin/password', 'Password', NULL, 6, 0, '', '', 1, 0)
						->values(9, 'admin/pages', 'Pages', NULL, 6, 0, '', '', 4, 0)
						->values(10, 'admin/config', 'Configuration', NULL, 6, 0, '', '', 4, 0)
						->values(11, 'forgot-password', 'Recover Password', NULL, NULL, 1, '', '', 0, 0)
						->result();
				}
			}
			return false;
		}
		
		/**
		*  Get the collection of protected pages that are required by Canteen
		*  @method getProtectedUris
		*  @return {Array} The collection protected page URIs
		*/
		public function getProtectedUris()
		{
			return array(
				'401', 
				'403', 
				'404', 
				'500', 
				'home',
				'admin',
				'admin/users',
				'admin/password',
				'admin/pages',
				'admin/config',
				'forgot-password'
			);
		}

		/**
		*  Add a new page to the site
		*  @method addPage
		*  @param {Dictionary|String} propertiesOrUri The collection of page properties
		*  @param {String} [title=''] The page title
		*  @param {String} [description=''] The description of the page
		*  @param {String} [keywords=''] The collection of keywords for meta
		*  @param {int} [privilege=0] The minimum privilege required to view this page
		*  @param {int|null} [redirectId=null] Always redirect to another page
		*  @param {int|null} [parentId=null] Optional page to specify as the parent (default is null at the root level)
		*  @param {Boolean} [isDynamic=false] If the page can have additional arguments after the stub URI
		*  @param {Boolean} [cache=true] If the page should always respect the site cache
		*  @return {int|Boolean} The ID if successful, false if not
		*/
		public function addPage($propertiesOrUri, $title='', $keywords='', $description='', $privilege=0, $redirectId=null, $parentId=null, $isDynamic=0, $cache=1)
		{
			$properties = $propertiesOrUri;

			if (!is_array($properties))
			{
				$properties = array(
					'uri' => $properties,
					'title' => $title, 
					'keywords' => $keywords,
					'description' => $description,
					'privilege' => $privilege,
					'redirectId' => $redirectId,
					'parentId' => $parentId,
					'isDynamic' => $isDynamic,
					'cache' => $cache
				);
			}

			// Normally the add function would get the page id
			// but in this case we need to use as the default
			// parent id, if it's top level page
			$id = $this->db->nextId($this->table, 'page_id');
			$parentId = ifsetor($properties['parentId'], $id);
			$properties['id'] = $id;

			return $this->call($properties);
		}

		/**
		*  Get a current page by the URI stub
		*  @method getPage
		*  @param {String} uri Page's URI stub or collection of URIs
		*  @return {Page|Array} The collection of Page objects or a single Page
		*/
		public function getPage($id)
		{
			return $this->call($id);
		}

		/**
		*  Get all of the children pages nested within a page
		*  @method getPagesByParentId
		*  @param {int} parentId A page's parent ID
		*  @return {Array} The collection of Page objects
		*/
		public function getPagesByParentId($id)
		{
			return $this->call($id);
		}

		/**
		*  Get all of the pages
		*  @method getPages
		*  @return {Array} The collection of Page objects
		*/
		public function getPages()
		{
			return $this->call();
		}

		/**
		*  Remove a page 
		*  @method removePage
		*  @param {id} id The Page ID to remove
		*  @return {Boolean} If page was deleted successfully
		*/
		public function removePage($id)
		{
			return $this->call($id);
		}

		/**
		*  Update a user property or properties
		*  @method updatePage
		*  @param {int} id The page ID
		*  @param {String|Dictionary} prop The property name or an array of property => value
		*  @param {mixed} [value=null] The value to update to
		*  @return {Boolean} If successfully updated
		*/
		public function updatePage($id, $prop, $value=null)
		{
			return $this->call($id, $prop, $value);
		}
	}
}