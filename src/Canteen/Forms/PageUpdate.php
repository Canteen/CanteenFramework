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
		*/
		public function __construct()
		{		
			$pageId = $this->verify(ifsetor($_POST['pageId']));
			$page = $this->service('pages')->getPage($pageId);
			
			// See if we're going to delete the page
			// if the delete button was clicked
			if (isset($_POST['deleteButton']))
			{
				// Make sure there's a valid page
				if (!$page)
				{
					$this->error('No page to delete');
				}
				else if (in_array($page->uri, $this->service('pages')->getProtectedUris()))
				{
					$this->error('Page is protected an cannot be deleted');
				}
				else
				{
					// Remove the page
					if (!$this->service('pages')->removePage($pageId))
					{
						$this->error('Unable to delete page');
					}
					
					// If we can write to the 
					if (file_exists($page->contentUrl) && is_writable($page->contentUrl))
					{
						$removed = @unlink($page->contentUrl);
						if ($removed === false)
						{
							$this->error('Unable to delete page contents');
						}
					}			
				}
				
				if (!$this->ifError)
				{
					// Goto the main pages admin
					redirect('admin/pages');
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
			$parentId = $this->verify(ifsetor($_POST['parentId'], 0));
			$redirectId = $this->verify(ifsetor($_POST['redirectId'], 0));
			
			// Default both the new and old content to be empty
			if ($page) $page->content = '';
			$content = '';
			
			// The write directory where to save HTML files
			$dir =  $this->settings->contentPath;
			$isWritable = is_writable($this->settings->callerPath . $dir) !== false;
			$contentUrl = $dir . $uri . '.html';
			
			// Make sure the content path is writeable
			if ($isWritable)
			{
				$content = ifsetor($_POST['pageContent']);
				if ($page) $page->content = @file_get_contents($page->contentUrl);
			}
			
			if ($privilege < Privilege::ANONYMOUS || $privilege > Privilege::ADMINISTRATOR)
				$this->error('Not a valid privilege');
			
			if (!$uri) $this->error('URI is a required field');
				
			if (!$title) $this->error('Title is a required field');
			
			// Don't process if we have errors
			if ($this->ifError) return;
			
			// Update if there's a current page
			if ($page)
			{
				// if the parent id is the current id the parentid should be 0
				// well pull the parent id as current
				if ($page->parentId == $pageId) $page->parentId = 0;
				
				// The protected pages, you cannot change the uri
				$protected = in_array($page->uri, $this->service('pages')->getProtectedUris());
				$protectedProperties = array('uri', 'parentId', 'redirectId', 'privilege');
				
				// Change for changes in properties
				$properties = array();
				
				foreach(array('title', 'uri', 'keywords', 'description', 'isDynamic', 'privilege', 'parentId', 'redirectId', 'cache') as $p)
				{
					// Ignore protected properties on protected pages
					if ($protected && in_array($p, $protectedProperties)) continue;
					
					if ($$p != $page->$p) 
					{
						$properties[$p] = $$p;
					}
				}
				
				// Boolean if the content changed from the original
				$contentChanged = ($content != $page->content);
								
				if (!$contentChanged && !count($properties))
				{
					$this->error('Nothing to update');
					return;
				}
								
				if (is_writable($contentUrl))
				{
					if ($uri != $page->uri && file_exists($page->contentUrl))
					{
						// If the content change, we an just delete the old file
						if ($contentChanged)
						{
							@unlink($page->contentUrl);
						}
						// If the content didn't change, just move the file
						else
						{
							@rename($page->contentUrl, $contentUrl);
						}	
					}
					
					if ($contentChanged)
					{
						$success = @file_put_contents($contentUrl, $content);
						if ($success === false)
						{
							$this->error('Unable to update the page content ' . $contentUrl);
						}
					}
				}
				
				if (count($properties))
				{
					$result = $this->service('pages')->updatePage($pageId, $properties);

					if (!$result)
					{
						$this->error('Unable to update page');	
					}
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
				
				if ($isWritable && $content)
				{
					$success = @file_put_contents($contentUrl, $content);
					if ($success === false)
					{
						$this->error('Unable to update the page content');
					}
				}
				
				if (!$result)
				{
					$this->error('Unable to add the page');
				}
			}
			
			if (!$this->ifError) redirect('admin/pages');
		}
	}
}