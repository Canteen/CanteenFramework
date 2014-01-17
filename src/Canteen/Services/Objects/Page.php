<?php

/**
*  @module Canteen\Services\Objects
*/
namespace Canteen\Services\Objects
{
	/**
	*  This is a generic data object that represents the page.  Located in the namespace __Canteen\Services\Objects__.
	*  
	*  @class Page
	*/
	class Page
	{
		/** 
		*  The page index id 
		*  @property {}
		*/
		public $id;
	
		/** 
		*  The uri of the page, this is the whole uri, even parent 
		*  @property {String} uri
		*/
		public $uri;
	
		/** 
		*  The thing that goes in the address bar 
		*  @property {String} title
		*/
		public $title = '';
		
		/** 
		*  The full site title with the page 
		*  @property {String} fullTitle
		*/
		public $fullTitle = '';
	
		/** 
		*  The html content for the page 
		*  @property {String} content
		*/
		public $content = '';
		
		/** 
		*  The path to the html content file 
		*  @property {String} contentUrl
		*/
		public $contentUrl;
	
		/** 
		*  The description of the page 
		*  @property {String} description
		*/
		public $description;
	
		/** 
		*  The keywords for the page 
		*  @property {String} keywords
		*/
		public $keywords;
	
		/** 
		*  If the page has a parent page 
		*  @property {int} parentId
		*/
		public $parentId;
	
		/** 
		*  If the page is dynamic uri, has content after it, like other ids 
		*  @property {Boolean} isDynamic
		*/
		public $isDynamic;
		
		/** 
		*  The dynamic part of the uri 
		*  @property {String} dynamicUri
		*/
		public $dynamicUri;
	
		/** 
		*  If this page redirects to sub page 
		*  @property {int} redirectId
		*/
		public $redirectId;
		
		/** 
		*  Boolean if the page should be cached 
		*  @property {Boolean} cache
		*/
		public $cache = true;
		
		/** 
		*  The minimum privilege required to view this page 
		*  @property {int} privilege
		*/
		public $privilege;
		
		/** 
		*  The page id for the site's body id 
		*  @property {id} pageId
		*/
		public $pageId;
	}
}