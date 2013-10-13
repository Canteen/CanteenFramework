<?php

/**
*  @module Canteen\Forms
*/
namespace Canteen\Forms
{
	use Canteen\Authorization\Privilege;
	use Canteen\Utilities\Validate;
	
	/**
	*  Update or add a user.  Located in the namespace __Canteen\Forms__.
	*  @class PageUpdate
	*  @extends FormBase
	*/
	class PageUpdate extends FormBase
	{
		/**
		*  Process the form and handle the $_POST data.
		*  @method process 
		*/
		public function process()
		{				
			$pageId = $this->verify(ifsetor($_POST['pageId']));
			$page = $this->service('pages')->getPageById($pageId);
			
			// See if we're going to delete the page
			// if the delete button was clicked
			if (isset($_POST['deleteButton']))
			{
				// Make sure there's a valid page
				if (!$page)
				{
					$this->error("No page to delete");
				}
				else if (in_array($page->uri, $this->service('pages')->getProtectedUris()))
				{
					$this->error("Page is protected an cannot be deleted");
				}
				else
				{
					// Remove the page
					if (!$this->service('pages')->removePage($pageId))
					{
						$this->error("Unable to delete page");
					}
					else
					{
						// Goto the main pages admin
						redirect('admin/pages');
					}
				}
				return;
			}
			
			$privilege = $this->verify(ifsetor($_POST['privilege']));
			$uri = $this->verify(ifsetor($_POST['uri']), Validate::URI);
			$title = $this->verify(ifsetor($_POST['title']), Validate::FULL_TEXT);
			$keywords = $this->verify(ifsetor($_POST['keywords']), Validate::FULL_TEXT);			
			$description = $this->verify(ifsetor($_POST['description']), Validate::FULL_TEXT);
			$isDynamic = isset($_POST['isDynamic']);
			$cache = isset($_POST['cache']);
			$parentId = $this->verify(ifsetor($_POST['parentId']));
			$redirectId = $this->verify(ifsetor($_POST['redirectId']));
			
			if ($privilege < Privilege::ANONYMOUS || $privilege > Privilege::ADMINISTRATOR)
				$this->error("Not a valid privilege");
			
			if (!$uri) $this->error("URI is a required field");
				
			if (!$title) $this->error("Title is a required field");
			
			// Don't process if we have errors
			if ($this->ifError()) return;
			
			// Update user
			if ($page)
			{
				$properties = array();
				
				foreach(array('title', 'uri', 'keywords', 'description', 'isDynamic', 'privilege', 'parentId', 'redirectId', 'cache') as $p)
				{
					if ($$p != $page->$p) $properties[$p] = $$p;
				}
				
				if (!count($properties))
				{
					$this->error("Nothing to update");
					return;
				}
				
				$result = $this->service('pages')->updatePage($pageId, $properties);
				
				if (!$result)
				{
					$this->error("Unable to update page");
				}
				else
				{
					$this->success("Updated page");
					redirect('admin/pages');
				}
			}
			// Add new page
			else
			{
				$result = $this->service('pages')->addPage(
					$uri, 
					$title, 
					$keywords, 
					$description, 
					$privilege, 
					$redirectId, 
					$parentId, 
					$isDynamic,
					$cache
				);
				
				if (!$result)
				{
					$this->error("Unable to add the page");
				}
				else
				{
					$this->success("Added new page");
					redirect('admin/pages');
				}
			}
		}
	}
}