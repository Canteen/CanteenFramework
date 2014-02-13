<?php

/**
*  @module Canteen\Controllers
*/
namespace Canteen\Controllers
{
	use Canteen\Utilities\CanteenBase;
	use Canteen\Errors\CanteenError;
	use Canteen\Services\Objects\Page;
	
	/**
	*  Handle dynamic content and page requests. 
	*  Located in the namespace __Canteen\Controllers__.
	*  
	*  @class Controller
	*  @extends CanteenBase
	*  @constructor
	*  @param {Page} page The current page object
	*  @param {String} [dynamicUri=''] The dynamic URI for which page.isDynamic is true
	*/
	abstract class Controller extends CanteenBase
	{
		/** 
		*  The the current page 
		*  @property {Page} page
		*  @protected
		*/
		protected $page;
		
		/** 
		*  For dynamic pages, the extra stuff after the page URI 
		*  @property {String} dynamicUri
		*  @protected
		*/
		protected $dynamicUri;
		
		/** 
		*  Any custom substitutions for the parser 
		*  @property {Dictionary} data
		*  @protected
		*/
		protected $data = [];
		
		/**
		*  Process the controller, all controllers should extend this function
		*  @method process
		*/
		public function process()
		{
			throw new CanteenError(CanteenError::OVERRIDE_CONTROLLER_PROCESS);
		}

		/**
		*  Set the current page
		*  @method setPage
		*  @param {Page} page The page to build with this Controller
		*/
		public function setPage(Page $page)
		{
			$this->page = $page;
			$this->dynamicUri = $page->dynamicUri;
		}

		/**
		*  Get the current page object
		*  @method getPage
		*  @return {Page} The updated page object
		*/
		public function getPage()
		{
			return $this->page;
		}
		
		/**
		*  Get the data variables
		*  @method getData
		*  @return {Dictionary} Data variables for substitution
		*/
		public function getData()
		{
			return $this->data;
		}
		
		/**
		*  Prepend to the page title
		*  @method addTitle
		*  @param {String} title The title string to prepend
		*  @param {String} [separator=.] The title separator, default is a period
		*/
		protected function addTitle($title, $separator='.')
		{
			$this->page->title = $title . " $separator " . $this->page->title;
		}
		
		/**
		*  Add a data substitution variable
		*  @method addData
		*  @param {String|Dictionary|Object} name The name of the data variable or an array of (name=>value, name=>value)
		*  @param {mixed} value The string of the value (optional if setting one data property)
		*/
		public function addData($name, $value=null)
		{
			if (is_object($name))
			{
				$name = get_object_vars($name);
			}
				
			if (is_array($name))
			{
				foreach($name as $i=>$v)
				{
					$this->addData($i, $v);
				}
			}
			else
			{
				$this->data[$name] = $value;
			}
		}
	}
}