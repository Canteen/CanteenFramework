<?php

/**
*  @module Canteen\Forms
*/
namespace Canteen\Forms
{
	use Canteen\Utilities\CanteenBase;
	use Canteen\Utilities\StringUtils;
	use Canteen\Errors\CanteenError;
	use Canteen\Errors\UserError;
	use Canteen\HTML5\SimpleList;
	use \Exception;
	
	/**
	*  This handles the processing and message reporting for all the
	*  site forms.  Located in the namespace __Canteen\Forms__.
	*  @class FormFactory
	*  @extends CanteenBase
	*/
	class FormFactory extends CanteenBase
	{
		/** 
		*  class of ul for form errors 
		*  @property {String} ERROR
		*  @final
		*/
		const ERROR = 'formError';
	
		/** 
		*  class of ul for form successes 
		*  @property {String} SUCCESS
		*  @final
		*/
		const SUCCESS = 'formSuccess';
	
		/** 
		*  Contain the success message 
		*  @property {Array} _successMessages
		*  @private
		*/
		private $_successMessages;
	
		/** 
		*  Contain the form errors 
		*  @property {Array} _errorMessages
		*/
		private $_errorMessages;
	
		/** 
		*  Contain the form data to return 
		*  @property {Array} _formData
		*  @private
		*/
		private $_formData;
	
		/**
		*  Setup the collections
		*/
		public function __construct()
		{
			$this->_formData = array();
			$this->_errorMessages = array();
			$this->_successMessages = array();
		}
		
		/**
		*  Allow only certain forms to be run before site loads
		*  @method startup
		*  @param {String} forms* Collection of forms that can run before anything else
		*/
		public function startup($forms)
		{
			$forms = is_array($forms) ? $forms : func_get_args();
			$form = ifsetor($_POST['form']);
			
			if ($form && in_array($form, $forms))
			{
				$this->process($form);
			}
		}
		
		/**
		*  Process any forms
		*  @method process
		*  @param {String} formClass The form id (e.g. LoginForm = login-form)
		*  @param {Boolean} [async=false] If the request to process is an ajax-style request
		*  @return {Object} If the request is async, the result object
		*/
		public function process($formClass, $async=false)
		{			
			//Before process any form request, we check that only
			//posts are sent from the current domain
			//because its possible to spoof HTTP_REFERER 
			//this shouldn't be relied on from people spoofing forms
			
			$host = ifconstor('HOST', '//'.ifsetor($_SERVER['HTTP_HOST']));
			$refer = ifsetor($_SERVER['HTTP_REFERER']);
			$refer = substr($refer, strpos($refer, ':')+1);
			
			if (strpos($refer, $host) !== 0) 
			{
				throw new CanteenError(CanteenError::WRONG_DOMAIN, $refer);
		   	}
		   			
			if (!class_exists($formClass)) 
			{
				throw new CanteenError(CanteenError::INVALID_FORM, $formClass);
			}
			
			if (!in_array('Canteen\Forms\FormBase', class_parents($formClass)))
			{
				throw new CanteenError(CanteenError::FORM_INHERITANCE, $formClass);
			}
			
			// if we are providing a random session id, register form session
			// this is to prevent refreshing the form
			if (isset($_POST['formSession']))
			{
				$_SESSION['formSession'] = ifsetor($_SESSION['formSession'], array());

				if ( in_array($_POST['formSession'], $_SESSION['formSession']) )
				{
					$this->error("You cannot refresh this form.");
				}
				else
				{
					$_SESSION['formSession'][] = (string)$_POST['formSession'];
				}
			}
			
			if (!$this->ifError)
			{
				try
				{
					$form = new $formClass;
				}
				catch(UserError $e)
				{
					// Report user errors to the user
					$this->error($e->getMessage());
				}
				catch(Exception $e)
				{
					// Bubble up other errors
					// something went wrong, like mysql or syntax error
					throw $e;
				}
			}
			
			// If there's no form error then flush the whole cache
			// this might be a little over-doing it
			// but is sufficient for now
			if (!$this->ifError && $this->cache)
			{
				$this->cache->flush();
			}

			if (!$async) return;
			
			return json_encode(
				array(
					'type' => 'formFeedback',
					'data' => ifsetor($this->_formData),
					'ifError' => $this->ifError,
					'messages' => $this->ifError ? 
						ifsetor($this->_errorMessages):
						ifsetor($this->_successMessages)
				)
			);
		}
	
		/**
		*  Report a form error
		*  @method error
		*  @param {String} message The message error to report
		*/
		public function error($message)
		{
			$this->_errorMessages[] = $message;
		}
	
		/**
		*  Report a success message
		*  @method success
		*  @param {String} message The message to report success
		*/
		public function success($message)
		{
			$this->_successMessages[] = $message;
		}
	
		/**
		*  The getter 
		*/
		public function __get($name)
		{
			/**
			*  If the form has an error
			*  @property {Boolean} ifError
			*  @readOnly
			*/
			if ($name == 'ifError')
			{
				return count($this->_errorMessages) > 0;
			}
			return parent::__get($name);
		}
		
			
		/**
		*  Return the feedback (errors and successes) as a list 
		*  @method getFeedback
		*  @return {String} The html string with errors in a UL list
		*/
		public function getFeedback()
		{					
			$list = '';
			if (count($this->_errorMessages))
			{
				$list = new SimpleList($this->_errorMessages, 'class='.self::ERROR);
			}
			else if (count($this->_successMessages))
			{
				$list = new SimpleList($this->_successMessages, 'class='.self::SUCCESS);
			}
			return (string)$list;
		}
	
		/** 
		*  Set data associated with a form, can be retrieved when rendering the page
		*  using the getData method
		*  @method setData
		*  @param {String} name The name of the variable to set
	 	*  @param {mixed} value The value of the data
		*/ 
		public function setData($name, $value)
		{
			$this->_formData[$name] = $value;
		}
	
		/**
		*  Get data associated with a form
		*  @method getData
		*  @param {String} The name of the parameter to get
		*  @return {mixed} The value of the data
		*/
		public function getData($name)
		{
			return ifsetor($this->_formData[$name], '');
		}
	}
}