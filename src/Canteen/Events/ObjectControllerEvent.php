<?php

namespace Canteen\Events
{
	use Canteen\Controllers\ObjectFormElement as Element;

	/**
	*  Events for the form object
	*  @class ObjectControllerEvent
	*  @extends Event
	*  @constructor
	*  @param {String} type The type of event
	*  @param {ObjectFormElement} [element=null] The element reference
	*/
	class ObjectControllerEvent extends Event
	{
		/** 
		*  The reference to the object 
		*  @property {ObjectFormElement} element
		*/
		public $element;

		/**
		*  Add an element to the admin controller form
		*  @event addElement
		*  @property {String} ADD_ELEMENT
		*  @final
		*  @static
		*/
		const ADD_ELEMENT = 'addElement';

		/**
		*  Element was added to the admin controller form
		*  @event addedElement
		*  @property {String} ADDED_ELEMENT
		*  @final
		*  @static
		*/
		const ADDED_ELEMENT = 'addedElement';

		/**
		*  When all of the fields have been added, before the closing
		*  @event completed
		*  @property {String} COMPLETED
		*  @final
		*  @static
		*/
		const COMPLETED = 'completed';

		/**
		*  Before element are being added
		*  @event beginAdding
		*  @property {String} BEING_ADDING
		*  @final
		*  @static
		*/
		const BEING_ADDING = 'beginAdding';

		/**
		*  Constructor
		*/
		public function __construct($type, Element $element=null)
		{
			parent::__construct($type);

			$this->element = $element;
		}
	}
}