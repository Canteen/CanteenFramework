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
		*  @param {String} uri The path to the call
		*  @param {callable} call The callback for the uri request
		*  @param {int} [privilege=Privilege::ANONYMOUS] The minimum privilege needed to call
		*/
		public function register($uri, $call, $privilege=Privilege::ANONYMOUS)
		{
			if (isset($this->_controls[$uri]))
			{
				throw new GatewayError(GatewayError::REGISTERED_URI, $uri);
			}
			$this->_controls[$uri] = new GatewayControl($uri, $call, $privilege);
		}

		/**
		*  The main server handler
		*  @method handle
		*  @param {String} uriRequest The entire URI being requested
		*  @return {Object} The JSON object
		*/
		public function handle($uriRequest)
		{
			try
			{
				$result = $this->internalHandle($uriRequest);
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
			
			return json_encode($output);
		}
		
		/**
		*  The internal JSON handle request
		*  @method internalHandle
		*  @private
		*  @param {String} uriRequest The URI request being made
		*  @return {mixed} The result of the service call  
		*/	
		private function internalHandle($uriRequest)
		{
			if ($uriRequest == $this->uri.'/' || $uriRequest == $this->uri)
			{
				throw new GatewayError(GatewayError::NO_INPUT); 
			}

			if (strpos($uriRequest, $this->uri.'/') !== 0)
			{
				throw new GatewayError(GatewayError::BAD_URI_REQUEST, [$uriRequest, $this->uri]);
			}

			$request = str_replace($this->uri.'/', '', $uriRequest);

			foreach($this->_controls as $control)
			{
				if ($control->match($request)) break;
				$control = null;
			}

			if (!$control)
			{
				throw new GatewayError(GatewayError::NO_CONTROL_FOUND, $request);
			}
			else if ($control->privilege > USER_PRIVILEGE)
			{
				throw new GatewayError(GatewayError::INSUFFICIENT_PRIVILEGE, $control->uri);
			}

			// Get the arguments
			$args = $control->getArguments($request);

			// Check for valid number of parameters
			$numArgs = count($args);

			// Get the number of parameters
			if (($args && ($numArgs < $control->required || $numArgs > $control->total)) 
				|| (!$args && $control->required))
					throw new GatewayError(GatewayError::INCORRECT_PARAMETERS);

			return $args ? 
				call_user_func_array($control->call, $args) : 
				call_user_func($control->call);
		}
	}
}