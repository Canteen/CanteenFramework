<?php

/**
*  @module Canteen\Server
*/
namespace Canteen\Server
{
	use Canteen\Services\Service;
	use Canteen\Utilities\Validate;
	use Canteen\Utilities\CanteenBase;
	use Canteen\Utilities\StringUtils;
	use Canteen\Errors\JSONServerError;
	use \Exception;
	use \ReflectionClass;
	
	/**
	*  This server handles requests made through the gateway and returns JSON data.  Located in the namespace __Canteen\Server__.
	*  
	*  @class JSONServer
	*  @extends CanteenBase
	*/
	class JSONServer extends CanteenBase
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
		*  The main server handler
		*  @method handle
		*  @return {Object} The JSON object
		*/
		public function handle()
		{
			try
			{
				$result = $this->internalHandle();
				$errorCode = null;
				$type = self::SUCCESS;
			}
			// The JSON Server specific errors
			catch(JSONServerError $e)
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
			
			$output = array(
				'result' => $result,
				'type' => $type
			);
			
			// See if there's an error code
			if ($errorCode !== null) $output['errorCode'] = $errorCode;
			
			return json_encode($output);
		}
		
		/**
		*  The internal JSON handle request
		*  @method internalHandle
		*  @private
		*  @return {mixed} The result of the service call  
		*/	
		private function internalHandle()
		{
			// Default to using the get, could also handle posting	
			$scope = $this->processURI();
			$aliases = Service::getAliases();
			
			// There should be a service and call name
			$serviceName = isset($scope['service']) ? $scope['service'] : '';
			$callName = isset($scope['call']) ? $scope['call'] : '';
			
			// Sanitize the names
			Validate::verify($serviceName, Validate::URI);
			Validate::verify($callName, Validate::URI);
			
			// If there are no parameters, return nothing
			if (!$serviceName && !$callName) return;
			
			if (Service::get($serviceName))
			{
				// Get the service that's already available
				$service = Service::get($serviceName);
			}
			// Check the aliases
			else if ($aliases && isset($aliases[$serviceName]))
			{
				$service = new $aliases[$serviceName];
			}
			else
			{
				if (!$serviceName) 
					throw new JSONServerError(JSONServerError::INVALID_SERVICE);
				
				// Make a new service from the service name
				$service = new $serviceName;
			}
		
			// Check for a valid method call
			if (!$callName || !method_exists($service, $callName)) 
				throw new JSONServerError(JSONServerError::INVALID_METHOD, $callName);
		
			// Make sure our service is a real service and not a php API
			if (!($service instanceof Service)) 
				throw new JSONServerError(JSONServerError::SERVICE_ERROR);
		
			$call = array($service, $callName);
		
			// Get the arguments (optional)
			$args = isset($scope['args']) && $scope['args'] ? $scope['args'] : '';
		
			// Check for valid number of parameters
			$reflector = new ReflectionClass(get_class($service));
			$method = $reflector->getMethod($callName);
			$requiredParams = $method->getNumberOfRequiredParameters();
			$totalParams= $method->getNumberOfParameters();
			$numArgs = count($args);
			
			// Get the number of parameters
			if (($args && ($numArgs < $requiredParams || $numArgs > $totalParams)) 
				|| (!$args && $requiredParams))
			{
				throw new JSONServerError(JSONServerError::INCORRECT_PARAMETERS);
			}
			
			// Finally make the call
			return $args ? 
				call_user_func_array($call, $args) : 
				call_user_func($call);
		}
	
		/**
		*  Process the URI as an array with different pieces
		*  @method processURI
		*  @return {Array} The URI where each stub part is a different element in the Array
		*/
		public function processURI()
		{			
			$uri = explode('/', $this->settings->uriRequest);
			$uri = array_slice($uri, 1); // don't use the name of the page
			
			// Sanitize the result to remove non charaters
			for($i = 0; $i < count($uri); $i++)
			{
				$uri[$i] = preg_replace('/[^a-zA-Z0-9\_\-\,%]/', '', $uri[$i]);
			}
			
			// Grab the rest of the uri arguments
			$args = count($uri) > 2 ? array_slice($uri, 2) : '';
			
			// Check to see if we should make an array of any of the arguments
			if ($args)
			{
				foreach($args as $i => $arg)
				{
					$arg = trim(urldecode($arg));
					if (strpos($arg, ',') !== false)
					{
						$arg = explode(',', $arg);
					}
					$args[$i] = $arg;
				}
			}
			
			// Turn into a result
			return array(
				'service' => ifsetor($uri[0], ''),
				'call' => StringUtils::uriToMethodCall(ifsetor($uri[1], '')),
				'args' => $args
			);
		}
	}
}