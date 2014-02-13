<?php

/**
*  @module Canteen\Forms
*/
namespace Canteen\Forms
{
	use Canteen\Authorization\Privilege;
	use Canteen\Utilities\Validate;
	use Canteen\Events\ObjectFormEvent;
	
	/**
	*  Update or add a user.  Located in the namespace __Canteen\Forms__.
	*  @class PageForm
	*  @extends ObjectForm
	*/
	class PageForm extends ObjectForm
	{
		/**
		*  Constructor
		*/
		public function __construct()
		{
			$this->on(ObjectFormEvent::VALIDATE, [$this, 'onValidate'])
				->on(ObjectFormEvent::BEFORE_REMOVE, [$this, 'onBeforeRemove'])
				->on(ObjectFormEvent::BEFORE_UPDATE, [$this, 'onBeforeUpdate'])
				->on(ObjectFormEvent::REMOVED, [$this, 'onRemoved'])
				->on(ObjectFormEvent::ADDED, [$this, 'onAdded']);

			parent::__construct(
				$this->service('page')->item,
				$this->settings->uriRequest
			);
		}

		/**
		*  Event handler before a page is removed
		*  @method onBeforeRemove
		*  @param {ObjectFormEvent} event The before remove event
		*/
		public function onBeforeRemove(ObjectFormEvent $event)
		{
			if (in_array($event->object->uri, $this->item->service->getProtectedUris()))
			{
				$this->error('Page is protected an cannot be deleted');
			}
		}

		/**
		*  Event handler after a page is removed
		*  @method onRemoved
		*  @param {ObjectFormEvent} event The removed event
		*/
		public function onRemoved(ObjectFormEvent $event)
		{
			if (file_exists($event->object->contentUrl) && is_writable($event->object->contentUrl))
			{
				$removed = @unlink($event->object->contentUrl);
				if ($removed === false)
				{
					$this->error('Unable to delete page contents');
				}
			}	
		}

		/**
		*  Handler to do some validation before an add or update
		*  @method onValidate
		*  @param {ObjectFormEvent} event The beforeAdd or beforeUpdate event
		*/
		public function onValidate(ObjectFormEvent $event)
		{
			// Set checkbox defaults
			$redirectId = ifsetor($_POST['redirectId'], 0);
			$parentId = ifsetor($_POST['parentId'], 0);

			// Check for required fields
			$uri = ifsetor($_POST['uri']);
			$title = ifsetor($_POST['title']);
			$privilege = ifsetor($_POST['privilege']);

			if (!$uri)
			{
				$this->error('URI is a required field');
			}
			else if (preg_match('/[^a-zA-Z0-9\-\_]/', $uri))
			{
				$uri = null;
				$this->error('URI can only contain letters, numbers, hypens and underscores');
			}
			if (!$title) 
			{
				$this->error('Title is a required field');
			}

			if ($privilege < Privilege::ANONYMOUS || $privilege > Privilege::ADMINISTRATOR)
			{
				$this->error('Not a valid privilege');
			}

			if ($uri)
			{
				// Append the parent to the URI
				if ($parentId)
				{
					if ($parent = $this->item->service->getPage($parentId))
					{
						$uri = $_POST['uri'] = $parent->uri . '/' . $uri;
					}
					else
					{
						$this->error('The parent ID is invalid.');
					}
				}

				// Page is already selected
				if ($event->object)
				{
					// Update the page contents
					$event->object->content = @file_get_contents(
						$this->settings->contentPath . $uri . '.html'
					);
				}
			}
		}

		/**
		*  Handler to do some extra privilege checking before we update
		*  @method onBeforeUpdate
		*  @param {ObjectFormEvent} event The beforeUpdate event
		*/
		public function onBeforeUpdate(ObjectFormEvent $event)
		{
			$page = $event->object;

			// if the parent id is the current id the parentid should be 0
			// well pull the parent id as current
			if ($page->parentId == $page->id) 
				$page->parentId = 0;

			// The protected pages, you cannot change the uri
			$protected = in_array($page->uri, $this->item->service->getProtectedUris());
			
			// For protected page we can't override some properties
			// like privilege, redirect, parentId and uri
			if ($protected)
			{
				$protectedProperties = ['uri', 'parentId', 'redirectId', 'privilege'];

				foreach($protectedProperties as $prop)
				{
					// See if the property changed
					// if it did, we should invalidate the posted value
					if (isset($_POST[$prop]) && $_POST[$prop] != $page->$prop)
					{
						$_POST[$prop] = $event->object->$prop;
					}
				}
			}

			$dir =  $this->settings->contentPath;
			$isWritable = is_writable($this->settings->callerPath . $dir) !== false;
			$contentUrl = $dir . $page->uri . '.html';

			if (is_writable($contentUrl))
			{
				$content = ifsetor($_POST['content']);
				$contentChanged = $content != $page->content;

				if (file_exists($page->contentUrl))
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
					else
					{
						$this->success('Updated page content');
						$this->updateNothingError = false;
					}
				}
			}
		}

		/**
		*  Handler write the content file after we added
		*  @method onAdded
		*  @param {ObjectFormEvent} event The added event
		*/
		public function onAdded(ObjectFormEvent $event)
		{
			$success = @file_put_contents(
				$event->object->contentUrl, 
				ifsetor($_POST['content'])
			);

			if ($success === false)
			{
				$this->error('Unable to update the page content');
			}
		}
	}
}