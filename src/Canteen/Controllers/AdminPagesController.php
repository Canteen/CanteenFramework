<?php

/**
*  @module Canteen\Controllers
*/
namespace Canteen\Controllers
{
	use Canteen\Authorization\Privilege;
	
	/** 
	*  Controller to manage the page management form.
	*  Located in the namespace __Canteen\Controllers__.
	*  
	*  @class AdminPagesController
	*  @extends AdminController
	*/
	class AdminPagesController extends AdminController
	{
		/**
		*  Process the controller and build the view
		*  @property {Array} allPages
		*  @private
		*/
		private $allPages;
		
		/**
		*  Process the controller and build the view
		*  @method process
		*/
		public function process()
		{			
			$this->allPages = $this->service('pages')->getPages();
			$page = null;
			$pageId = (int)ifsetor($_POST['pageId']);
			
			if (!empty($pageId))
			{
				$page = $this->service('pages')->getPageById($pageId);
			}		
			
			$data = array(
				'formLabel' => $page ? 'Update an Existing Page' : 'Add a New Page',
				'buttonLabel' => $page ? 'Update' : 'Add',
				'isDynamic' => '',
				'pages' => '',
				'hasPage' => false,
				'readOnly' => false
			);
			
			if ($page)
			{
				$protected = in_array($page->uri, $this->service('pages')->getProtectedUris());
				$data['privileges'] = $this->getPrivileges($page->privilege);
				$data['id'] = $page->id;
				$data['title'] = $page->title;
				$data['redirectId'] = $this->getPages($page->redirectId, $page->id);
				$data['parentId'] = $this->getPages($page->parentId, $page->id);
				$data['uri'] = $page->uri;
				$data['description'] = $page->description;
				$data['keywords'] = $page->keywords;
				$data['isDynamic'] = $page->isDynamic ? 'checked' : '';
				$data['hasPage'] = true;
				$data['readOnly'] = $protected ? 'disabled' : '';
				$data['cache'] = $page->cache ? 'checked' : '';
			}
			else
			{
				$pages = $this->getPages();
				$data['redirectId'] = $pages;
				$data['parentId'] = $pages;
				$data['privileges'] = $this->getPrivileges();
				$data['pages'] = $pages;
			}
			
			// Update the page with the template
			$this->addTemplate('AdminPages', $data);
		}
		
		/**
		*  Get the form select of privileges
		*/
		private function getPrivileges($privilege = 0)
		{
			$options = '';
			$privileges = Privilege::getAll();
			foreach($privileges as $i=>$label)
			{
				$option = html('option value='.$i, $label);
				if ($i == $privilege)
				{
					$option->selected = 'selected';
				}
				$options .= (string)$option;
			}
			return $options;
		}
		
		/**
		*  Get the users as options
		*  @method getPages
		*  @private
		*  @param {int} [selectId=null] Select this optional page ID
		*  @param {int} [ignoreId=null] Ignore optional page ID
		*/
		private function getPages($selectId=null, $ignoreId=null)
		{
			$options = '';
			foreach($this->allPages as $page)
			{
				if ($ignoreId == $page->id) continue;
				
				$option = html('option value='.$page->id, $page->title);
				if ($selectId == $page->id)
				{
					$option->selected = 'true';
				}
				$options .= (string)$option;
			}
			return $options;
		}
	}
}