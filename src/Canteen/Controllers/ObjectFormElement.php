<?php

/**
*  @module Canteen\Controllers
*/
namespace Canteen\Controllers
{
	use Canteen\Utilities\StringUtils;

	/**
	*  Represents a single form element
	*  @class ObjectFormElement
	*  @constructor 
	*  @param {String} name The name of the field
	*  @param {String} value The value of the element
	*  @param {int} tabIndex The tabIndex
	*/
	class ObjectFormElement
	{
		/**
		*  The name of the Object item field
		*  @property {String} name
		*/
		public $name = '';

		/**
		*  The value associated with an object's value
		*  @property {String} value
		*/
		public $value = '';

		/**
		*  The human-readable label
		*  @property {String} label
		*/
		public $label = '';

		/**
		*  For input type's, the name of the element (e.g., text, password, date)
		*  @property {String} type
		*/
		public $type = '';

		/**
		*  The template name
		*  @property {String} template
		*/
		public $template;

		/**
		*  For checkboxes, the additional description
		*  @property {String} description
		*/
		public $description = '';

		/**
		*  Additional CSS classes to add on the element
		*  @property {String} classes
		*/
		public $classes = '';

		/**
		*  The tab index value
		*  @property {int} tabIndex
		*/
		public $tabIndex = 0;

		/**
		*  The constructor
		*
		*/
		public function __construct($name, $value, $tabIndex)
		{
			$this->name = $name;
			$this->value = $value;
			$this->label = StringUtils::propertyToReadable($name);
			$this->tabIndex = $tabIndex;
		}
	}
}