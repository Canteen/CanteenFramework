<?php

/*
*  @module Canteen\Events
*/
namespace Canteen\Events
{
	class Event
	{
		/**
		*  Whether no further event listeners should be triggered
		*  @property {Boolean} propagationStopped
		*  @private
		*/
		private $propagationStopped = false;

		/**
		*  This event's type
		*  @property {String} type
		*/
		public $type;

		/**
		*  Event is the base class for classes containing event data.
		*  This class contains no event data. It is used by events that do not pass
		*  state information to an event handler when an event is raised.
		*  You can call the method `stopPropagation()` to abort the execution of
		*  further listeners in your event listener.
		*  @class Event
		*  @constructor
		*  @param {String} type The type of this event being dispatched
		*/
		public function __construct($type)
		{
			$this->type = $type;
		}

		/**
		*  Returns whether further event listeners should be triggered.
		*  @method isPropagationStopped
		*  @return {Boolean} Whether propagation was already stopped for this event.
		*/
		public function isPropagationStopped()
		{
			return $this->propagationStopped;
		}

		/**
		* Stops the propagation of the event to further event listeners.
		*
		* If multiple event listeners are connected to the same event, no
		* further event listener will be triggered once any trigger calls
		* stopPropagation().
		*
		* @method stopPropagation
		*/
		public function stopPropagation()
		{
			$this->propagationStopped = true;
		}
	}
}

