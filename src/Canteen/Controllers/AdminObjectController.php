<?php

/**
*  @module Canteen\Controllers
*/
namespace Canteen\Controllers
{	
	use Canteen\Services\Objects\Page;
	use Canteen\Services\ObjectServiceItem;
	use Canteen\Utilities\Validate;
	use Canteen\Events\ObjectControllerEvent as Event;

	/**
	*  Admin controller for standard object editing
	*  Located in the namespace __Canteen\Controllers__.
	*  
	*  @class AdminObjectController
	*  @extends AdminController
	*  @abstract
	*  @constructor
	*  @param {ObjectServiceItem} item The item render with controller
	*  @param {String} formName The full class name of the form
	*  @param {String} [titleName='title'] The name of the object field to use as select title
	*/
	abstract class AdminObjectController extends AdminController
	{
		/**
		*  The name of the item field for use in the select form list
		*  @property {String} titleName
		*  @protected
		*  @default 'title'
		*/
		protected $titleName;

		/**
		*  The name of the full form 
		*  @property {String} formName
		*  @protected
		*/
		protected $formName;

		/**
		*  The collection of field names which are optional to input
		*  @property {Array} optionalFields
		*  @private
		*/
		private $_optionalFields = [];

		/**
		*  The collection of field names which we should ignore render for
		*  @property {Array} ignoreFields
		*  @private
		*/
		private $_ignoreFields = [];

		/**
		*  The method name on the item service to get the object by id
		*  @property {String} getObject
		*  @protected
		*/
		protected $getObject;

		/**
		*  The method name on the item service to get all objects
		*  @property {String} getObjects
		*  @protected
		*/
		protected $getObjects;

		/**
		*  Create the controller and attach a page, item
		*/
		public function __construct(ObjectServiceItem $item, $formName, $titleName='title')
		{
			$this->item = $item;
			$this->formName = $formName;
			$this->titleName = $titleName;
			$this->getObject =  'get'.$this->item->itemName;
			$this->getObjects = 'get'.$this->item->itemsName;
			$this->ignoreFields($this->item->defaultField->name);
		}

		/**
		*  Ignore field names 
		*  @method ignoreFields
		*  @param {String} fields* The field name as separate arguments
		*/
		public function ignoreFields($fields)
		{
			$this->_ignoreFields = array_merge($this->_ignoreFields, func_get_args());
		}

		/**
		*  Don't add the required class to these fields
		*  @method optionalFields
		*  @param {String} fields* The field name as separate arguments
		*/
		public function optionalFields($fields)
		{
			$this->_optionalFields = array_merge($this->_optionalFields, func_get_args());
		}

		/**
		*  The process method contains nothing
		*  navigation is created when renderNavigation is called.
		*  @method process
		*/
		public function process()
		{
			$id = null;

			$defaultField = $this->item->defaultField;
			$defaultName = $defaultField->name;

			// Incase this is a dynamic URI
			if ($this->dynamicUri)
			{
				$id = $this->dynamicUri;
			}
			// From the select form
			else if (isset($_POST[$defaultName]))
			{
				$id = $_POST[$defaultName];
			}

			$object = $id ? $this->item->service->{$this->getObject}($id) : null;

			// Settup required preoperties
			$data = [
				'hasObject' => (bool)$object,
				'formName' => $this->formName,
				'formLabel' => (bool)$object ? 'Edit' : 'Add',
				'defaultName' => $defaultName,
				'objectDefaultValue' => $object ? $object->$defaultName : '',
				'objectType' => strtolower($this->item->itemName),
				'objectLabel' => $this->item->itemName,
				'cancelUri' => $this->page->uri,
				'cancelRefresh' => !$this->dynamicUri ? 'data-refresh="soft"' : '',
				'objects' => '',
				'object' => $object
			];

			if (!$object)
			{
				$objects = $this->item->service->{$this->getObjects}();

				if ($objects)
				{
					$result = '';
					foreach($objects as $o)
					{
						$result .= html('option value="'.$o->$defaultName.'"', $o->{$this->titleName});
					}
					$data['objects'] = $result;
				}		
			}

			// Add the footer of the form, this include the select form
			$this->addTemplate('AdminObjectHeader', $data);

			// We should create an empty object to use 
			if (!$object) $object = new $this->item->className;

			$this->trigger(new Event(Event::BEING_ADDING));

			$tabIndex = 1;

			// Add form elements here!
			foreach($this->item->fieldsByName as $name=>$field)
			{
				// Ignore the id
				if (in_array($name, $this->_ignoreFields)) continue;

				// Create a new form element
				$element = new ObjectFormElement(
					$name, 
					$object->$name,
					$tabIndex++
				);

				$element->objectType = $data['objectType'];

				if (!in_array($name, $this->_optionalFields))
				{
					$element->classes .= 'required ';
				}

				if ($field->type == Validate::BOOLEAN)
				{
					$element->classes = '';
					$element->value = intval($element->value) ? 'checked' : '';
					$element->template = 'ObjectCheckbox';
				}
				else if ($field->type == Validate::MYSQL_DATE)
				{
					if (preg_match('/^[0-9}{4}\-[0-9]{2}\-[0-9]{2}$/', $element->value))
					{
						$element->value = strftime('%Y-%m-%d', strtotime($element->value));
						$element->type = 'date';
					}
					else
					{
						$element->value = strftime('%Y-%m-%dT%H:%M:%S', strtotime($element->value));
						$element->type = 'datetime-local';
					}
					$element->template = 'ObjectText';
				}
				else if ($field->type == null)
				{
					$element->template = 'ObjectTextArea';
				}
				else if (is_array($field->type))
				{
					$element->template = 'ObjectSelect';
				}
				else
				{
					$element->type = 'text';
					$element->template = 'ObjectText';
				}

				// Fire event here
				$this->trigger(new Event(Event::ADD_ELEMENT, $element));

				// Increment the tabIndex number
				$tabIndex = $element->tabIndex + 1;

				// Add the element to the display
				$this->addElement($element);

				// Fire the finished event
				$this->trigger(new Event(Event::ADDED_ELEMENT, $element));
			}

			$this->trigger(new Event(Event::COMPLETED));

			// Include the footer and buttons
			$this->addTemplate('AdminObjectFooter', $data);
		}

		/**
		*  Add a custom form element
		*  @method addElement
		*  @protected
		*  @param {ObjectFormElement} element The form element to add
		*/
		protected function addElement(ObjectFormElement $element)
		{
			// Render the template
			$this->addTemplate($element->template, $element);
		}
	}
}