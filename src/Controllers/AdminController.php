<?php

/**
*  @module Canteen\Controllers
*/
namespace Canteen\Controllers
{
	use Canteen\Parser\Parser;
	use Canteen\HTML5\SimpleList;
	
	/**
	*  For processing videos pages.
	*  Located in the namespace __Canteen\Controllers__.
	*  
	*  @class AdminController
	*  @extends Controller
	*/
	class AdminController extends Controller
	{
		/** 
		*  Check to see if we've already been pre-rendered 
		*  @property {Boolean} _isPrerendered
		*  @private
		*/
		private $_isPrerendered = false;
		
		/**
		*  The process method contains nothing
		*  navigation is created when renderNavigation is called.
		*  @method process
		*/
		public function process()
		{
			// Do nothing
		}
		
		/**
		*  Add the admin page by template, if there is no template
		*  use the default prerender() method
		*  @method addTemplate
		*  @protected
		*  @param {String} template The template name
		*  @param {Dictionary} [data=array()] The optional substitution variables
		*/
		protected function addTemplate($template, $data=array())
		{
			$this->page->content .= Parser::getTemplate($template, $data);
		}
		
		/**
		*  Get the current page object, this is an override
		*  before returning the page, we render the navigation
		*  @method getPage
		*  @return {Page} The updated page object
		*/
		public function getPage()
		{
			// Make sure we only prerender once
			if (!$this->_isPrerendered) $this->prerender();
			
			return $this->page;
		}
		
		/**
		*  Pre-render the admin page with the navigation
		*  @method pre-render
		*  @private
		*/
		private function prerender()
		{
			$this->_isPrerendered = true;
			
			$pages = $this->service('pages')->getPagesByParentId($this->page->parentId);
			$custom = array();
			$builtIn = array();
			
			$protected = $this->service('pages')->getProtectedUris();
			
			foreach($pages as $child)
			{
				if (USER_PRIVILEGE >= $child->privilege)
				{
					$link = html('a', $child->title, 'href='.BASE_PATH.$child->uri);
					if ($child->uri == $this->page->uri)
					{
						$link->class = 'selected';
					}
					
					if (in_array($child->uri, $protected))
					{
						$link->class .= ' builtIn';
						$builtIn[] = $link;
					}
					else
					{
						$link->class .= ' custom';
						$custom[] = $link;
					}
				}
			}
			
			$data = array(
				'adminNavCustom' => count($custom) ? (string)new SimpleList($custom, 'class=custom') : false,
				'adminNav' => (string)new SimpleList($builtIn, 'class=builtIn'),
				'adminContent' => Parser::parse($this->page->content, $this->getData())
			);
			
			$this->page->content = Parser::getTemplate('Admin', $data); 
		}
	}
}