<?php

/**
*  @module Canteen\Server
*/
namespace Canteen\Server
{
	use Canteen\Authorization\Privilege;
	use Canteen\Errors\GatewayError;
	use \Exception;
	
	/** 
	*  This server handles requests made through the gateway and returns JSON data.  
	*  Located in the namespace __Canteen\Server__.
	*  
	*  @class Gateway
	*/
	class Gateway
	{		
		/** 
		*  If the output produced an error 
		*  @property {String} ERROR
		*  @static
		*  @final
		*/
		const ERROR = 'error';
		
		/** 
		*  If the output was successful 
		*  @property {String} SUCCESS
		*  @static
		*  @final
		*/
		const SUCCESS = 'success';

		/** 
		*  The map of controls by uri alias
		*  @property {Dictionary} _controls
		*  @private
		*/
		private $_controls = [];

		/**
		*  The path to this gateway server
		*  @property {String} uri
		*  @default 'gateway';
		*/
		public $uri = 'gateway';

		/**
		*  Create a new gateway
		*  @method register
		*  @param {String} call The path to the call
		*  @param {callable} handler The callback for the uri request
		*  @param {int} [privilege=Privilege::ANONYMOUS] The minimum privilege needed to call
		*/
		public function register($call, $handler, $privilege=Privilege::ANONYMOUS)
		{
			$this->_controls[$call] = new GatewayControl($call, $handler, $privilege);
		}

		/**
		*  The main server handler
		*  @method handle
		*  @param {String} call The name of the method alias
		*  @return {Object} The JSON object
		*/
		public function handle($call)
		{
			try
			{
				$result = $this->internalHandle($call);
				$errorCode = null;
				$type = self::SUCCESS;
			}
			// The JSON Server specific errors
			catch(GatewayError $e)
			{
				$result = $e->getMessage();
				$errorCode = $e->getCode();
				$type = self::ERROR;
			}
			// Bubble-up other exceptions
			catch(Exception $e)
			{
				throw $e;
			}
			
			$output = [
				'result' => $result,
				'type' => $type
			];
			
			// See if there's an error code
			if ($errorCode !== null) $output['errorCode'] = $errorCode;
			
			echo json_encode($output);
		}
		
		/**
		*  The internal JSON handle request
		*  @method internalHandle
		*  @private
		*  @param {String} call The name of the method alias
		*  @return {mixed} The result of the service call  
		*/	
		private function internalHandle($call)
		{
			$control = ifsetor($this->_controls[$call]);

			if (!$control)
			{
				throw new GatewayError(GatewayError::NO_CONTROL_FOUND, $call);
			}
			else if ($control->privilege > USER_PRIVILEGE)
			{
				throw new GatewayError(GatewayError::INSUFFICIENT_PRIVILEGE, $control->uri);
			}

			// Get the arguments
			$args = $control->getArguments($call);

			// Check for valid number of parameters
			$numArgs = count($args);

			// Get the number of parameters
			if (($args && ($numArgs < $control->required || $numArgs > $control->total)) 
				|| (!$args && $control->required))
					throw new GatewayError(GatewayError::INCORRECT_PARAMETERS);

			return $args ? 
				call_user_func_array($control->handler, $args) : 
				call_user_func($control->handler);
		}
	}
}