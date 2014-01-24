<?php

/**
*  @module Canteen\Forms
*/
namespace Canteen\Forms
{
	use Canteen\Forms\Form;
	use Canteen\Events\ObjectFormEvent;
	use Canteen\Services\ObjectServiceItem;

	/**
	*  Generalized form for processing ObjectServiceItem objects
	*  @class ObjectForm
	*  @extends Form
	*  @constructor
	*  @param {ObjectServiceItem} item The reference to a valid service object
	*  @param {String} redirect The redirect URI on success
	*/
	abstract class ObjectForm extends Form
	{
		/**
		*  The object service item reference
		*  @property {ObjectServiceItem} item
		*  @protected
		*/
		protected $item;

		/**
		*  The redirect uri after remove or add
		*  @property {String} redirect
		*  @protected
		*/
		protected $redirect;

		/**
		*  If we should redirect after an add
		*  @property {Boolean} addRedirect
		*  @protected
		*  @default true
		*/
		protected $addRedirect = true;

		/**
		*  If we should redirect after an update
		*  @property {Boolean} updateRedirect
		*  @protected
		*  @default true
		*/
		protected $updateRedirect = true;

		/**
		*  If we should redirect after a remove
		*  @property {Boolean} removeRedirect
		*  @protected
		*  @default true
		*/
		protected $removeRedirect = true;

		/**
		*  If we should do the update nothing error
		*  @property {Boolean} updateNothingError
		*  @protected
		*  @default true
		*/
		protected $updateNothingError = true;

		/**
		*  Constuctor
		*/
		public function __construct(ObjectServiceItem $item, $redirect=null)
		{
			$this->redirect = ifsetor($_POST['redirect'], $redirect);
			$action = ifsetor($_POST['action']);
			$this->item = $item;

			if (!$action || !method_exists($this, $action))
			{
				$this->error("No valid action matching '$action'");
				$this->failed();
			}
			else
			{
				$this->$action();
			}
		}

		/**
		*  Check to see if we've failed
		*  @param {Object} object The object, optional
		*  @return {Boolean} True if we failed
		*/
		private function failed($object=null)
		{
			if ($this->ifError)
			{
				$this->trigger(new ObjectFormEvent(ObjectFormEvent::FAILED, $object));
				return true;
			}
			return false;
		}

		/**
		*  Generalized remove function
		*  @method remove
		*  @protected
		*/
		protected function remove()
		{
			$id = ifsetor($_POST[$this->item->defaultField->name]);
			$object = $this->getObject($id);
			
			if (!$object)
			{
				$this->error('No valid '.$this->item->itemName.' found');
				$this->failed();
				return;
			}

			$this->trigger(new ObjectFormEvent(ObjectFormEvent::BEFORE_REMOVE, $object));
			
			if ($this->failed($object)) return;

			$method = 'remove'.$this->item->itemName;
			if (!$this->item->service->$method($id))
			{
				$this->error('Unable to remove '.$this->item->itemName);
				$this->failed($object);
				return;
			}
			
			$this->trigger(new ObjectFormEvent(ObjectFormEvent::REMOVED, $object));

			// Always redirect if the update was successful
			if ($this->failed($object)) return;
			
			if ($this->redirect !== null && $this->removeRedirect)
			{
				redirect($this->redirect);
			}
			else
			{
				$this->success('Removed ' . $this->item->itemName . ' successfully');
			}
		}

		/**
		*  Update the object
		*  @method update
		*  @protected
		*/
		protected function update()
		{
			$id = ifsetor($_POST[$this->item->defaultField->name]);
			$object = $this->getObject($id);
			
			if (!$object)
			{
				$this->error("No valid {$this->item->itemName} matching id '$id'");
				$this->failed();
				return;
			}

			$this->trigger(new ObjectFormEvent(ObjectFormEvent::VALIDATE, $object));

			if ($this->failed($object)) return;

			$this->trigger(new ObjectFormEvent(ObjectFormEvent::BEFORE_UPDATE, $object));

			if ($this->failed($object)) return;

			$properties = [];

			// Get the object variables
			$fields = $this->item->fieldsByName;

			foreach($fields as $name=>$field)
			{
				if (isset($_POST[$name]) && $_POST[$name] != $object->$name)
				{
					$properties[$name] = $_POST[$name];
				}
			}

			if (!count($properties))
			{
				if ($this->updateNothingError) 
					$this->error('Nothing to update');

				$this->failed($object);
				return;
			}

			$method = 'update'.$this->item->itemName;
			if (!$this->item->service->$method($id, $properties))
			{
				$this->error('Unable to update ' . $this->item->itemName);
				$this->failed($object);
				return;
			}

			$this->trigger(new ObjectFormEvent(ObjectFormEvent::UPDATED, $object));

			// if the update was successful, optionally we can redirect
			// to a page of choice or just stay here and report the success
			if ($this->failed($object)) return;

			if ($this->redirect !== null && $this->updateRedirect)
			{
				redirect($this->redirect);
			}
			else
			{
				$this->success('Updated ' . $this->item->itemName.' successfully');
			}
		}

		/**
		*  Add a new object
		*  @method add
		*  @protected
		*/
		protected function add()
		{
			$this->trigger(new ObjectFormEvent(ObjectFormEvent::VALIDATE));

			if ($this->failed()) return;

			$this->trigger(new ObjectFormEvent(ObjectFormEvent::BEFORE_ADD));

			if ($this->failed()) return;

			$properties = [];

			foreach($this->item->fieldsByName as $name=>$field)
			{
				if (isset($_POST[$name]))
				{
					$properties[$name] = $_POST[$name];
				}
			}

			if (!count($properties))
			{
				$this->error('Nothing to add');
				$this->failed();
				return;
			}

			$method = 'add'.$this->item->itemName;
			$id = $this->item->service->$method($properties);

			if (!$id)
			{
				$this->error('Unable to add new '.$this->item->itemName);
				$this->failed();
				return;
			}

			$this->trigger(new ObjectFormEvent(ObjectFormEvent::ADDED, $this->getObject($id)));

			if ($this->redirect !== null && $this->addRedirect)
			{
				redirect($this->redirect);
			}
			else
			{
				$this->success('Added ' . $this->item->itemName.' successfully');
			}
		}

		/**
		*  Generalized get object by ID
		*  @method getObject
		*  @private
		*  @param {int} id The unique object id
		*  @return {Object} The object, or null
		*/
		private function getObject($id)
		{
			if (!$id)
			{
				$this->error('ID is required');
				$this->failed();
				return;
			}
			$method = 'get'.$this->item->itemName;
			return $this->item->service->$method($id);
		}
	}
}