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
		*  You can run to make sure a process requires a particular privilege
		*  @method privilege
		*  @protected
		*  @param {int} [required=Privilege::GUEST] The privilege level required, default is anonymous
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
		*  @method verify
		*  @protected
		*  @param {mixed} data The data to be validated, can be an array of items
		*  @param {RegExp} [type=null] The type of validation, defaults to Numeric. Can also be an array set of items
		*  @param {Boolean} [suppressErrors=false] If we should suppress throwing errors
		*  @return {mixed} If we don't verify and suppress errors, returns false, else returns the data
		*/
		protected function verify($data, $type=null, $suppressErrors=false)
		{
			return Validate::verify($data, $type, $suppressErrors);
		}
		
		/**
		*  Convenience function for passing an error to the form factory
		*  @method error
		*  @protected
		*  @param {String} message The str error to pass
		*/
	    protected function error($message)
	    {
	        $this->site->formFactory->error($message); 
	    }

	    /**
		*  Convenience function for passing a success message to the form factory
		*  @method success
		*  @protected
		*  @param {String} message The str success message to pass
		*/
	    protected function success($message) 
	    { 
	        $this->site->formFactory->success($message);
	    }

	    /**
	    *   Getter 
	    */
		public function __get($name)
		{
			/**
			*  If we've reported errors
			*  @property {Boolean} ifError
			*/
			if ($name == 'ifError')
			{
				return $this->site->formFactory->ifError;
			}
			return parent::__get($name);
		}


		/**
		*  Save the form data to the form factory
		*  @method getData
		*  @protected
		*  @param {String} name The name of the variable to save
		*  @param {mixed} value The value of the variable to save
		*/
	    protected function setData($name, $value) 
	    {
	        $this->site->formFactory->setData($name, $value);
	    }
	}
}