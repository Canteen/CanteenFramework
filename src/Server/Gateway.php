<?php

/**
*  @module Canteen\Server
*/
namespace Canteen\Server
{
	use Canteen\Authorization\Privilege;
	use Canteen\Errors\GatewayError;
	use Canteen\Utilities\Plugin;
	use \Exception;
	use flight\net\Route;
	
	/** 
	*  This server handles requests made through the gateway and returns JSON data.  
	*  Located in the namespace __Canteen\Server__.
	*  
	*  @class Gateway
	*  @extends Plugin
	*/
	class Gateway extends Plugin
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
		*  Turn on the gateway
		*  @method activate
		*/
		public function activate()
		{
			// Create a path to the gateway for the client
			$this->site->addSetting(
				'gatewayPath', 
				$this->settings->basePath . $this->uri, 
				SETTING_CLIENT
			);
		}

		/**
		*  Create a new gateway
		*  @method register
		*  @param {String} pattern The route pattern to check
		*  @param {callable} handler The callback for the uri request
		*  @param {int} [privilege=Privilege::ANONYMOUS] The minimum privilege needed to call
		*/
		public function register($pattern, $handler, $privilege=Privilege::ANONYMOUS)
		{
			$pattern = '/'.$this->uri.'/'.$pattern;
			
			// Convert the pattern
			$route = new Route($pattern, null, null, false);
			$route->matchUrl('');
			$newPattern = $route->pattern;

			$control = new GatewayControl($pattern, $handler, $privilege);
			$this->_controls[$newPattern] = $control;
			$this->site->route($pattern, [$this, 'handle']);
		}

		/**
		*  The main server handler
		*  @method handle
		*  @return {Object} The JSON object
		*/
		public function handle($args=null)
		{
			$this->settings->asyncRequest = true;

			try
			{
				// Get the captured argument
				$args = func_get_args();

				// Get the current route object to get the select pattern
				$route = $this->site->router()->current();

				// Do the internal call
				$result = $this->internalHandle($route->pattern, $args);
				$errorCode = null;

				// Null objects should be errors
				$type = $result !== null ? self::SUCCESS : self::ERROR;
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
		*  @param {String} pattern The router pattern
		*  @param {Array} args The collectino of arguments
		*  @return {mixed} The result of the service call  
		*/	
		private function internalHandle($pattern, $args)
		{
			$control = ifsetor($this->_controls[$pattern]);

			if (!$control)
			{
				throw new GatewayError(GatewayError::NO_CONTROL_FOUND, $pattern);
			}
			else if ($control->privilege > USER_PRIVILEGE)
			{
				throw new GatewayError(GatewayError::INSUFFICIENT_PRIVILEGE, $control->uri);
			}

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
