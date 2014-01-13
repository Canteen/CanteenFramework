<?php

namespace Canteen\Events
{
	/**
	*  Events for the form object
	*  @class ObjectFormEvent
	*  @extends Event
	*  @constructor
	*  @param {String} type The type of event
	*  @param {Object} [object=null] The optional object reference
	*/
	class ObjectFormEvent extends Event
	{
		/** 
		*  The reference to the object 
		*  @property {Object} object
		*/
		public $object;

		/**
		*  Fired before an object is removed can be used to do 
		*  any additional checks on an object, like permissions.
		*  @event beforeRemove
		*  @property {String} BEFORE_REMOVE
		*  @final
		*  @static
		*/
		const BEFORE_REMOVE = 'beforeRemove';

		/**
		*  Fired after an object is successfully removed can be used to do 
		*  any additional removals on additional tables.
		*  @event removed
		*  @property {String} REMOVED
		*  @final
		*  @static
		*/
		const REMOVED = 'removed';

		/**
		*  Before the object is updated, might do additional checks.
		*  @event beforeUpdate
		*  @property {String} BEFORE_UPDATE
		*  @final
		*  @static
		*/
		const BEFORE_UPDATE = 'beforeUpdate';

		/**
		*  After an object has been successfully updated.
		*  @event updated
		*  @property {String} UPDATED
		*  @final
		*  @static
		*/
		const UPDATED = 'updated';

		/**
		*  Before the object is added, might do additional checks, validation.
		*  @event beforeAdd
		*  @property {String} BEFORE_ADD
		*  @final
		*  @static
		*/
		const BEFORE_ADD = 'beforeAdd';

		/**
		*  After an object has been successfully added.
		*  @event added
		*  @property {String} ADDED
		*  @final
		*  @static
		*/
		const ADDED = 'added';

		/**
		*  Before update to add, this is done before add or write
		*  to do common validation between those two.
		*  @event validate
		*  @property {String} VALIDATE
		*  @final
		*  @static
		*/
		const VALIDATE = 'validate';

		/**
		*  Constructor
		*/
		public function __construct($type, $object=null)
		{
			parent::__construct($type);

			$this->object = $object;
		}
	}
}