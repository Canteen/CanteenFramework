<?php

/**
*  @module Canteen\Forms
*/
namespace Canteen\Forms
{
	use Canteen\Site;
	use Canteen\Utilities\CanteenBase;
	use Canteen\Utilities\Validate;	
	use Canteen\Errors\UserError;
	use Canteen\Errors\CanteenError;
	use Canteen\Authorization\Privilege;
	
	/**
	*  All forms inherit this class which allows for some error logging
	*  as well ass access to the CanteenBase methods.  Located in the namespace __Canteen\Forms__.
	*  @class FormBase
	*  @extends CanteenBase
	*/
	abstract class FormBase extends CanteenBase
	{
	    /** 
		*  The id of this form 
		*  @property {int} id
		*/
	    public $id;
	
		/**
		*  Get the instance of the form factory
		*  @method formFactory
		*  @return FormFactory object
		*/
		public function formFactory()
		{
			return Site::instance()->getFormFactory();
		}
		
		/**
		*	Forms must extend process
		*/
		public function process()
		{
			throw new CanteenError(CanteenError::OVERRIDE_FORM_PROCESS);
		}
		
		/**
		*  You can run to make sure a process requires a particular privilege
		*  @param The privilege level required, default is anonymous
		*/
		protected function privilege($required=Privilege::GUEST)
		{
			if (USER_PRIVILEGE < $required)
			{
				throw new UserError(UserError::INSUFFICIENT_PRIVILEGE);
			}
		}
		
		/**
		*  Sanitize input data using the validation types above
		*  @param The data to be validated, can be an array of items
		*  @param The type of validation, defaults to Numeric. Can also be an array set of items
		*  @param If we should suppress throwing errors
		*  @return If we don't verify and suppress errors, returns false, else returns the data
		*/
		protected function verify($data, $type=null, $suppressErrors=false)
		{
			return Validate::verify($data, $type, $suppressErrors);
		}
		
		/**
		*  Convenience function for passing an error to the form factory
		*  @param The str error to pass
		*/
	    protected function error($message)
	    {
	        $this->formFactory()->error($message); 
	    }

	    /**
		*  Convenience function for passing a success message to the form factory
		*  @param The str success message to pass
		*/
	    protected function success($message) 
	    { 
	        $this->formFactory()->success($message);
	    }

	    /**
		*  Check for any errors
		*  @return The boolean if we've reported errors
		*/
	    protected function ifError() 
	    {
	        return $this->formFactory()->ifError();
	    }


		/**
		*  Save the form data to the form factory
		*  @param The name of the variable to save
		*  @param The value of the variable to save
		*/
	    protected function setData($name, $value) 
	    {
	        $this->formFactory()->setData($name, $value);
	    }
	}
}