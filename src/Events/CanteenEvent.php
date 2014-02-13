<?php

/*
*  @module Canteen\Events
*/
namespace Canteen\Events
{
	class CanteenEvent extends Event
	{
		/**
		*  The initial tables were installed for the site, like pages
		*  config and users.
		*  @property {String} INSTALLED
		*  @static
		*  @final
		*/
		const INSTALLED = 'installed';

		/**
		*  see Canteen\Events\Event::__constructor
		*  @class Event
		*  @constructor
		*  @param {String} type The type of this event being dispatched
		*/
		public function __construct($type)
		{
			parent::__construct($type);
		}
	}
}