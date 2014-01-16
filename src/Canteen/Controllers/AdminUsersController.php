<?php

/**
*  @module Canteen\Controllers
*/
namespace Canteen\Controllers
{
	use Canteen\Authorization\Privilege;
	use Canteen\Events\ObjectControllerEvent;
	
	/** 
	*  Controller to manage the user management form.
	*  Located in the namespace __Canteen\Controllers__.
	*  
	*  @class AdminUsersController
	*  @extends AdminObjectController
	*/
	class AdminUsersController extends AdminObjectController
	{
		/**
		*  The constructor
		*/
		public function __construct()
		{
			parent::__construct(
				$this->service('user')->item,
				'Canteen\Forms\UserUpdate',
				'fullname'
			);

			// These fields aren't editable
			array_push($this->ignoreFields, 
				'frozen', 
				'login', 
				'forgotString', 
				'attempts'
			);

			array_push($this->optionalFields,
				'password'
			);

			$this->on(ObjectControllerEvent::ADD_ELEMENT, [$this, 'onElementAdd'])
				->on(ObjectControllerEvent::ADDED_ELEMENT, [$this, 'onElementAdded']);
		}


		/**
		*  Handle specific elements
		*  @method onElementAdded
		*  @param {ObjectControllerEvent} event
		*/
		public function onElementAdded(ObjectControllerEvent $event)
		{
			if ($event->element->name == 'password')
			{
				$repeat = new ObjectFormElement(
					'repeatPassword',
					'',
					$event->element->tabIndex + 1
				);
				$repeat->type = 'password';
				$repeat->template = 'ObjectText';

				$this->addElement($repeat);
			}
		}

		/**
		*  Handle specific elements
		*  @method onElementAdd
		*  @param {ObjectControllerEvent} event
		*/
		public function onElementAdd(ObjectControllerEvent $event)
		{
			$element = $event->element;

			if ($element->name == 'password')
			{
				$element->type = 'password';
				$element->value = ''; // don't output hash
			}
			// Make privilege a selection list, no an input
			else if ($element->name == 'privilege')
			{
				$element->template = 'ObjectSelect';
				$element->options = $this->getPrivileges($element->value);
			}
			else if ($element->name == 'isActive')
			{
				$element->description = 'User can login';
			}
		}
		
		/**
		*  Get the form select of privileges
		*  @method getPrivileges
		*  @private
		*  @param {int} [privilege=0] The current user's privileger
		*/
		private function getPrivileges($privilege = 0)
		{
			$options = '';
			$privileges = Privilege::getAll();
			foreach($privileges as $i=>$label)
			{
				if ($i < Privilege::GUEST) continue;
				$option = html('option value='.$i, $label);
				if ($i == $privilege)
				{
					$option->selected = 'selected';
				}
				$options .= (string)$option;
			}
			return $options;
		}
	}
}