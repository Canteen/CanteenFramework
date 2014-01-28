<?php

/**
*  @module Canteen\Controllers
*/
namespace Canteen\Controllers
{
	use Canteen\Authorization\Privilege;
	use Canteen\Events\ObjectControllerEvent;
	
	/** 
	*  Controller to manage the page management form.
	*  Located in the namespace __Canteen\Controllers__.
	*  
	*  @class AdminPageController
	*  @extends AdminObjectController
	*/
	class AdminPageController extends AdminObjectController
	{
		/**
		*  If the page is writeable
		*  @property {Boolean} isWriteable
		*  @private
		*/
		private $isWriteable;

		/**
		*  Certain properties are protected on certain build-in pages
		*  @property {Boolean} isProtected
		*  @private
		*/
		private $isProtected;

		/**
		*  If we are a readOnly page
		*  @property {String} readOnly
		*  @private
		*/
		private $readOnly;

		public function __construct()
		{
			parent::__construct(
				$this->service('page')->item,
				'Canteen\Forms\PageForm',
				'title'
			);

			$this->allObjects = $this->item->service->getPages();

			foreach($this->allObjects as $page)
			{
				$page->title .= ' ('.$page->uri.')';
			}

			$this->optionalFields(
				'redirectId',
				'parentId',
				'description',
				'keywords',
				'password'
			);

			$this->on(ObjectControllerEvent::START, [$this, 'onStart'])
				->on(ObjectControllerEvent::START_ELEMENTS, [$this, 'onStartElements'])
				->on(ObjectControllerEvent::ADD_ELEMENT, [$this, 'onElementAdd'])
				->on(ObjectControllerEvent::ADDED_ELEMENT, [$this, 'onElementAdded']);
		}

		/**
		*  Do some setup before we start adding anything
		*  @method onStart
		*  @param {ObjectControllerEvent} event The start event
		*/
		public function onStart(ObjectControllerEvent $event)
		{
			// See if the path for content is writeable
			$this->isWriteable = is_writeable(
				$this->settings->callerPath . $this->settings->contentPath
			);

			// If this page is protected
			$this->isProtected = in_array(
				$this->object->uri, 
				$this->item->service->getProtectedUris()
			);

			$this->removeEnabled = !$this->isProtected;
			$this->readOnly = $this->isProtected ? 'disabled' : '';

			// Don't change these properties on protected pages
			if ($this->isProtected)
			{
				$this->ignoreFields('parentId', 'redirectId', 'isDynamic', 'cache');
			}
		}

		/**
		*  Before any elements are added
		*  @method onStartElements
		*  @param {ObjectControllerEvent} event The start elements event
		*/
		public function onStartElements(ObjectControllerEvent $event)
		{
			if (!$this->isWriteable)
			{
				$this->addNotice('Unable to save or edit page contents in '
					.'<strong>'.$this->settings->contentPath.'</strong>'
					.', please make this directory writeable.');
			}
		}

		/**
		*  Modify existing elements beign added
		*  @method onElementAdd
		*  @param {ObjectControllerEvent} event The element addd event
		*/
		public function onElementAdd(ObjectControllerEvent $event)
		{
			$e = $event->element;
			$id = $this->object ? $this->object->id : null;

			switch($event->element->name)
			{
				case 'uri' :
				{
					$e->label = strtoupper($e->label);
					$e->classes .= $this->readOnly;
					$e->attributes = $this->readOnly;
					$e->value = basename($e->value);
					break;
				}
				case 'redirectId':
				{
					$e->label = 'Redirect';
					$e->options = $this->getPageOptions(
						$e->value, 
						$this->object->id, 
						true, 
						'Don\'t Redirect'
					);
					$e->template = 'ObjectSelect';
					break;
				}
				case 'parentId':
				{
					$e->label = 'Parent';
					$e->options = $this->getPageOptions(
						$e->value, 
						$this->object->id, 
						false, 
						'No Parent'
					);
					$e->template = 'ObjectSelect';
					break;
				}
				case 'isDynamic':
				{
					$e->description = 'Additional dynamic URI components';
					break;
				}
				case 'cache':
				{
					$e->description = 'Always try to cache the page';
					break;
				}
				case 'privilege':
				{
					$e->options = $this->getPrivileges($e->value);
					$e->template = 'ObjectSelect';
					$e->classes .= $this->readOnly;
					$e->attributes = $this->readOnly;
					break;
				}
			}
		}

		/**
		*  When an element is added, inject the content
		*  @method onElementAdded
		*  @param {ObjectControllerEvent} event The element addded event
		*/
		public function onElementAdded(ObjectControllerEvent $event)
		{
			if ($this->isWriteable && $event->element->name == 'uri')
			{
				$content = new ObjectFormElement(
					'content',
					'',
					$event->element->tabIndex + 1
				);
				$content->value = $this->pageContent();
				$content->type = 'password';
				$content->template = 'ObjectTextArea';
				$content->classes .= 'editable tall';
				$this->addElement($content);
			}
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
		*  Get the page contents from the path 
		*  @method getContents
		*  @private
		*  @return {String} The string for the page
		*/
		private function pageContent()
		{
			$path = $this->settings->callerPath . 
					$this->settings->contentPath .
					$this->object->uri . '.html';

			$contents = @file_get_contents($path);
			
			if ($contents === false)
			{
				return '';
			}
			return str_replace('{{', '&#123;&#123;', 
				str_replace('}}', '&#125;&#125;', 
					htmlentities($contents)
				)
			);
		}
		
		/**
		*  Get the users as options
		*  @method getPageOptions
		*  @private
		*  @param {int} [selectId=null] Select this optional page ID
		*  @param {int} [ignoreId=null] Ignore optional page ID
		*  @param {Boolean} [showProtected=true] If we should show the error pages in the options
		*  @param {String} initOption The initial zero option
		*  @return {String} The html options for the select element
		*/
		private function getPageOptions($selectId, $ignoreId, $showProtected, $initOption)
		{
			$protected = ['404', '401', '500', '403'];
			
			$options = html('option value=0', $initOption);

			foreach($this->allObjects as $page)
			{
				if ($ignoreId == $page->id || (!$showProtected && in_array($page->uri, $protected))) continue;
				
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