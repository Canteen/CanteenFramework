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
	*  @extends Service
	*/
	class PageService extends Service
	{	
		/** 
		*  The name of the table which contains the pages 
		*  @property {String} table
		*  @private
		*/
		private $table = 'pages';
		
		/** 
		*  The list of database field properties 
		*  @property {Array} table
		*  @private
		*/
		private $properties = array(
			'`page_id` as `id`',
			'`uri`',
			"REPLACE(`uri`, '/', '-') as `pageId`",
			'`title`',
			'`description`',
			'`keywords`',
			'CONCAT(`uri`,\'.html\') as `contentUrl`',
			'IF(`parent_id` is NULL || `parent_id`=0, `page_id`, `parent_id`) as `parentId`',
			'`redirect_id` as `redirectId`',
			'IF(`is_dynamic` > 0, 1, null) as `isDynamic`',
			'`privilege`',
			'IF(`cache` > 0, 1, null) as `cache`'
		);
		
		/** 
		*  The data object associated with each page 
		*  @property {String} className
		*  @private
		*/
		private $className = 'Canteen\Services\Objects\Page';
		
		/** 
		*  The prepend mappings 
		*  @property {Array} mappings
		*  @private
		*/
		private $mappings;
		
		/**
		*  Create the service
		*/
		public function __construct()
		{
			parent::__construct('pages');
						
			$this->mappings = array(
				'contentUrl' => $this->settings('contentPath')
			);
		}
		
		/**
		*  Install the pages table into the database
		*  @method install
		*/
		public function install()
		{		
			$this->internal('Canteen\Forms\Installer');
			
			if (!$this->db->tableExists('pages'))
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
					return $this->db->insert('pages')
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
		*  Get a current page by the id number
		*  @method getPageById
		*  @param {int} id Page's ID or collection of IDs
		*  @return {Page|Array} The collection of Page objects or a single Page
		*/
		public function getPageById($id)
		{
			$this->internal(
				'Canteen\Parser\PageBuilder', 
				'Canteen\Controllers\AdminPagesController'
			);
						
			$results = $this->db->select($this->properties)
				->from($this->table)
				->where('page_id in '.$this->valueSet($id))
				->results();
			
			return is_array($id) ?
				$this->bindObjects($results, $this->className, $this->mappings):
				$this->bindObject($results, $this->className, $this->mappings);
		}
		
		/**
		*  Get a current page by the URI stub
		*  @method getPageById
		*  @param {String} uri Page's URI stub or collection of URIs
		*  @return {Page|Array} The collection of Page objects or a single Page
		*/
		public function getPageByUri($uri)
		{
			$this->internal('Canteen\Forms\ConfigUpdate');
						
			$results = $this->db->select($this->properties)
				->from($this->table)
				->where('`uri` in '.$this->valueSet($uri, Validate::URI))
				->results();
			
			return is_array($uri) ?
				$this->bindObjects($results, $this->className, $this->mappings):
				$this->bindObject($results, $this->className, $this->mappings);
		}
		
		/**
		*  Get all of the children pages nested within a page
		*  @method getPagesByParentId
		*  @param {int} parentId A page's parent ID
		*  @return {Array} The collection of Page objects
		*/
		public function getPagesByParentId($parentId)
		{
			$this->internal('Canteen\Controllers\AdminController');
			
			$this->verify($parentId);
			$results = $this->db->select($this->properties)
				->from($this->table)
				->where('parent_id='.$parentId)
				->results();
				
			return $this->bindObjects($results, $this->className, $this->mappings);
		}
		
		/**
		*  Get all of the pages
		*  @method getPages
		*  @return {Array} The collection of Page objects
		*/
		public function getPages()
		{
			$this->internal(
				'Canteen\Parser\PageBuilder', 
				'Canteen\Controllers\AdminPagesController'
			);
			
			$results = $this->db->select($this->properties)
				->from($this->table)
				->results(true);
			
			return $this->bindObjects($results, $this->className, $this->mappings);
		}
		
		/**
		*  Remove a page 
		*  @method removePage
		*  @param {id} id The Page ID to remove
		*  @return {Boolean} If page was deleted successfully
		*/
		public function removePage($id)
		{
			$this->internal('Canteen\Forms\PageUpdate');
			$this->privilege(Privilege::ADMINISTRATOR);
			
			return $this->db->delete($this->table)
				->where('page_id in '.$this->valueSet($id))
				->result();
		}
		
		/**
		*  Add a new page to the site
		*  @method addPage
		*  @param {String} uri The URI stub
		*  @param {String} title The page title
		*  @param {String} keywords The collection of keywords for meta
		*  @param {int} [privilege=0] The minimum privilege required to view this page
		*  @param {int|null} [redirectId=null] Always redirect to another page
		*  @param {int|null} [parentId=null] Optional page to specify as the parent (default is null at the root level)
		*  @param {Boolean} [isDynamic=false] If the page can have additional arguments after the stub URI
		*  @param {Boolean} [cache=true] If the page should always respect the site cache
		*  @return {int|Boolean} The ID if successful, false if not
		*/
		public function addPage($uri, $title, $keywords, $description, $privilege=0, $redirectId=null, $parentId=null, $isDynamic=false, $cache=true)
		{
			$this->internal('Canteen\Forms\PageUpdate');
			$this->privilege(Privilege::ADMINISTRATOR);
			
			$id = $this->db->nextId($this->table, 'page_id');
			
			if ($parentId === null) $parentId = $id;
			
			return $this->db->insert($this->table)
				->values(array(
					'page_id' => $id,
					'uri' => $this->verify($uri, Validate::URI),
					'title' => $this->verify($title, Validate::FULL_TEXT),
					'redirect_id' => $this->verify($redirectId),
					'parent_id' => $this->verify($parentId),
					'is_dynamic' => $isDynamic ? 1 : 0,
					'keywords' => $this->verify($keywords, Validate::FULL_TEXT),
					'description' => $this->verify($description, Validate::FULL_TEXT),
					'privilege' => $this->verify($privilege),
					'cache' => $cache ? 1 : 0
				))
				->result() ? $id : false;
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
			$this->internal('Canteen\Forms\PageUpdate');
			$this->privilege(Privilege::ADMINISTRATOR);
			
			$this->verify($id);
			
			if (!is_array($prop))
			{
				$prop = array($prop => $value);
			}
			
			$properties = array();
			foreach($prop as $k=>$p)
			{
				$k = $this->verify($k, Validate::URI);
				$k = $this->convertPropertyNames($k);
				$properties[$k] = $this->verify($p, Validate::FULL_TEXT);
			}
			
			return $this->db->update($this->table)
				->set($properties)
				->where('`page_id`='.$id)
				->result();
		}
		
		/**
		*  Convert the public property names into table field names
		*  @method convertPropertyNames
		*  @private
		*  @param {String} prop The name of the public property
		*  @return {String} The database field name
		*/
		private function convertPropertyNames($prop)
		{
			$props = array(
				'isDynamic' => 'is_dynamic',
				'parentId' => 'parent_id',
				'redirectId' => 'redirect_id',
				'id' => 'page_id'
			);
			return isset($props[$prop]) ? 
				$props[$prop] : 
				$this->verify($prop, Validate::URI);
		}
	}
}