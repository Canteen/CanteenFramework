<?php

/**
*  @module Canteen\Events
*/
namespace Canteen\Events
{
	/**
  	*  Basic EventDispatcher adapated from Symphony's EventDispatcher
  	*  but simplified to mirror the Canteen Client dispatcher API.
  	*  @class EventDispatcher
	*/
	class EventDispatcher
	{
        /** 
      	*  The collection of listeners
      	*  @property {Array} listeners
      	*  @private
        */
		private $listeners = [];

        /**
      	*  The collection of sorted listeners
      	*  @property {Array} sorted
      	*  @private
        */
		private $sorted = [];

		/**
		*  Dispatches an event to all registered listeners.
		*  @method trigger
		*  @param {string|Event} event The name of the event to dispatch or
      	*    the Event object. The name of the event is the name of the method 
      	*    that is invoked on listeners.
		*  @return {Event}
		*/
		public function trigger($event)
		{
			if (is_string($event))
            {
				$event = new Event($event);
			}

			// There are not listeners
			if (!isset($this->listeners[$event->type]))
            {
				return $event;
			}

			// Dispatch the event
			$listeners = $this->getListeners($event->type);

			foreach($listeners as $listener)
			{
				call_user_func($listener, $event);

				if ($event->isPropagationStopped())
				{
					break;
				}
			}
			return $event;
		}

		/**
		*   Checks whether an event has any registered listeners.
		*   @method has
		*   @param {String} eventType The name of the event
		*   @return {Boolean} true if the specified event has any listeners, false otherwise
		*/
		public function has($eventType)
		{
			return (boolean) count($this->getListeners($eventType));
		}

		/**
		*   Adds an event listener that listens on the specified events.
		*   @method on
		*   @param {String|Array} eventType The event to listen on or collection of events
		*   @param {callable} listener  The listener
		*   @param {int} [priority=0]  The higher this value, the earlier an event
		*	 listener will be triggered in the chain
		*   @return {EventDispatcher} Reference reference of this for chaining
		*/
		public function on($eventType, $listener, $priority = 0)
		{
			if (is_array($eventType))
			{
				foreach($eventType as $e)
				{
					$this->on($e, $listener, $priority);
				}
				return $this;
			}
			$this->listeners[$eventType][$priority][] = $listener;
			unset($this->sorted[$eventType]);
			return $this;
		}

		/**
		*   Removes an event listener from the specified events.
		*   @method off
		*   @param {String|Array} eventType The event(s) to remove a listener from
		*   @param {callable} [listener=null] The listener to remove, if not specified
		*     removes all listeners with matching event type
		*   @return {EventDispatcher} Reference reference of this for chaining
		*/
		public function off($eventType, $listener=null)
		{
			// Recursive call if we have a collection of types
			if (is_array($eventType))
			{
				foreach ($eventType as $type)
				{
					$this->off($type, $listener);
				}
				return $this;
			}

			// Bail out if no registered listeners
			if (!isset($this->listeners[$eventType])) return;

			if ($listener === null)
			{
				unset($this->listeners[$eventType]);
			}
			else
			{
				foreach ($this->listeners[$eventType] as $priority => $listeners)
				{
					if (($key = array_search($listener, $listeners, true)) !== false)
					{
						unset($this->listeners[$eventType][$priority][$key], $this->sorted[$eventType]);
					}
				}
			}
			return $this;
		}

		/**
		*  Gets the listeners of a specific event or all listeners.
		*  @method getListeners
		*  @private
		*  @param {String} [eventType=null] The type of the event
		*  @return {Array} The event listeners for the specified event, or all event listeners by event name
		*/
		private function getListeners($eventType = null)
		{
			if ($eventType !== null)
			{
				if (!isset($this->sorted[$eventType]))
				{
					$this->sortListeners($eventType);
				}
				return $this->sorted[$eventType];
			}

			foreach (array_keys($this->listeners) as $eventType)
			{
				if (!isset($this->sorted[$eventType]))
				{
					$this->sortListeners($eventType);
				}
			}
			return $this->sorted;
		}

		/**
		*  Sorts the internal list of listeners for the given event by priority.
		*  @method sortListeners
		*  @private
		*  @param {String} eventType The name of the event.
		*/
		private function sortListeners($eventType)
		{
			$this->sorted[$eventType] = [];

			if (isset($this->listeners[$eventType]))
			{
				krsort($this->listeners[$eventType]);
				$this->sorted[$eventType] = call_user_func_array(
					'array_merge', 
					$this->listeners[$eventType]
				);
			}
		}
	}
}

