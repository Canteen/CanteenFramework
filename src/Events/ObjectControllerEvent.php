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
		*  Page rendering has begun
		*  @event start
		*  @property {String} START
		*  @final
		*  @static
		*/
		const START = 'start';

		/**
		*  When all of the fields and footer have been added
		*  @event completed
		*  @property {String} COMPLETED
		*  @final
		*  @static
		*/
		const COMPLETED = 'completed';

		/**
		*  Before element are being added
		*  @event startElements
		*  @property {String} START_ELEMENTS
		*  @final
		*  @static
		*/
		const START_ELEMENTS = 'startElements';

		/**
		*  All the dynamic elements have been added
		*  @event doneElements
		*  @property {String} DONE_ELEMENTS
		*  @final
		*  @static
		*/
		const DONE_ELEMENTS = 'doneElements';

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