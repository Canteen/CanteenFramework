<?php

/**
*  @module Canteen\Services
*/
namespace Canteen\Services
{
	use Canteen\Errors\CanteenError;
	use Canteen\Logger\Logger;
	use Canteen\Parser\Parser;
	use Canteen\Utilities\StringUtils;
	use Canteen\Utilities\CanteenBase;	
	use \ReflectionClass;
	use \Exception;
	
	/**
	*  A simple utility app for exploring services.
	*  Located in the namespace __ServiceBrowser__.
	*  @class ServiceBrowser
	*  @extends CanteenBase
	*/
	class ServiceBrowser extends CanteenBase
	{
		/** 
		*  These are the services built into Canteen
		*  @property {Array} termiteAliases 
		*  @private
		*/
		private $termiteAliases = array(
			'users',
			'pages',
			'time',
			'config'
		);
		
		/** 
		*  The collection of all service aliases, Canteen and Custom
		*  @property {Array} aliases
		*  @private
		*/
		private $aliases;
		
		/** 
		*  The full browser URI path
		*  @property {String} browserUri
		*  @private
		*/
		private $browserUri;
		
		/** 
		*  The full browser URI path
		*  @property {String} uri 
		*  @private
		*/
		private $uri;
		
		/**
		*  Get a service name by an alias
		*  @method getServiceNameByAlias
		*  @param {String} The alias
		*  @return {String} The name of the Service class
		*/
		private function getServiceNameByAlias($alias)
		{
			$aliases = array_merge($this->aliases, $this->termiteAliases);
			return ifsetor($aliases[$alias]);
		}
		
		/**
		*  Create the browser request
		*  @method handle
		*/
		public function handle()
		{
			$this->browserUri = $this->site->browserUri;
			$this->aliases = Service::getAliases();
			$this->uri = StringUtils::processURI(
				$this->settings->uriRequest, 
				count(explode('/', $this->browserUri))
			);
								
			// Generate the output, if any
			$output = '';
			$serviceName = '';
			$serviceAlias = '';
			
			if ($serviceAlias = ifsetor($this->uri['service']))
			{
				$serviceName = $this->getServiceNameByAlias($serviceAlias);
				$args = ifsetor($this->uri['args']);
				
				$argsName = $this->displayArgs($args);
				
				// if there's a call parse that
				if ($callAlias = ifsetor($this->uri['call'])) 
				{
					$service = $this->service($serviceName);
					
					$callName = StringUtils::uriToMethodCall($callAlias);					
					$numParams = 0;

					if (!method_exists($service, $callName))
					{
						$output .= html('span.prompt', 'No service call matching '.$callName);
					}
					else
					{
						$reflector = new ReflectionClass($serviceName);
						$parameters = $reflector->getMethod($callName)->getParameters();
						$numParams = count($parameters);
						$numRequiredParams = $numParams;
						
						$output .= html('h2', $serviceName.'.'.$callName.'('.$argsName.')');
						
						if ($numParams && !$args && $numParams != $args)
						{
							$inputs = '';
							$i = 0;
							foreach($parameters as $param)
							{
								$default = '';
								$className = 'required';
								if ($param->isDefaultValueAvailable())
								{
									$numRequiredParams--;
									$default = $param->getDefaultValue();
									if ($default === null) $default = 'null';
									if ($default === false) $default = 'false';
									$className = 'optional';
								}
								$inputType = $param->getName() == 'password' ? 'password' : 'text';
								$inputs .= html('label', $param->getName(), 'for:serviceInput'.$i);
								$input = html('input', array(
									'type' => $inputType,
									'id' => 'serviceInput'.$i,
									'class' => 'text '.$className,
									'name' => 'arguments[]',
									'value' => ifsetor($default, '')								
								));
								$inputs .= $input . html('br'); 
								$i++;
							}
							
							$fieldset = html('fieldset', array(
								html('legend', "$numParams additional argument(s) required for this method"),
								html('div', $inputs),
								html('div.formButtons', html('input.submit type=submit value="Call Service"'))
							));
							
							$action = $this->settings->basePath.$this->browserUri.'/'.$serviceAlias.'/'.$callAlias;
							$output .= html('form#formInputs method=get action='.$action, $fieldset);
						
							// If the function has defaults for all params,
							// we'll show the form AND the default output
							if ($numRequiredParams === 0)
							{
								$output .= $this->callMethod(array($service, $callName));
							}
						}
						else
						{
							$output .= $this->callMethod(array($service, $callName), $args);
						}
					}
				}
			}
			
			$dir = __DIR__.'/';
			
			$link = html('a','View Gateway');
			$link->href = $this->settings->basePath . str_replace(
				$this->browserUri,
				$this->site->gatewayUri, 
				$this->settings->uriRequest
			);
			$link->class = 'gateway';
			
			return $this->template(
				'ServiceBrowser',
				array(
					'output' => $output,
					'services' => $this->getServicesList(),
					'methods' => $this->getMethodsList($serviceName, $serviceAlias),
					'logger' => Logger::instance()->render(),
					'gatewayLink' => (string)$link
				)
			);
		}
		
		/**
		*  Call the method and get the result
		*  @method callMethod
		*  @private
		*  @param {String|Array} call The user function to call
		*  @param {Array} [args=null] The optional arguments to pass to the user function
		*  @return {String} The HTML result of the call or stack trace
		*/
		private function callMethod($call, $args=null)
		{
			try
			{
				$result = $args ? 
					call_user_func_array($call, $args) : 
					call_user_func($call);
				
				$return = print_r($result, true);
				$return = !$return ? 'null' : $return;
			}
			catch(Exception $e)
			{
				$result = CanteenError::convertToResult($e);
				return html('div', $e->getMessage() 
					. ' (code: '.$e->getCode().')'
					. (string)new SimpleList($result['stackTrace'], null, 'ol'), 'class=exception');
			}
			return html('pre', $return);
		}
		
		/**
		*  The name of the class
		*  @method getMethodsList
		*  @private
		*  @return {String} HTML mark-up for methods lis
		*/
		private function getMethodsList($serviceName, $serviceAlias)
		{
			$res = '';
			
			if (!$serviceName) return $res;
			
			// Get the list of methods
			$reflector = new ReflectionClass($serviceName);
			$methods = $reflector->getMethods();
			
			// Sort the methods alphabetically by name
			$names = array();
			foreach ($methods as $key => $method)
			{
			    $names[$key] = $method->name;
			}
			array_multisort($names, SORT_ASC, $methods);
			
			$ul = html('ul');
			
			foreach($methods as $method)
			{
				// For services ignore constructor, static and protected
				if ($method->isConstructor() 
					|| $method->isStatic() 
					|| $method->isProtected()
					|| $method->isPrivate()
					|| substr($method->name, 0, 2) == '__') continue;
				
				$link = html('a', $method->name);
				$link->href = $this->settings->basePath . $this->browserUri.'/'.$serviceAlias.'/'.StringUtils::methodCallToUri($method->name);				
				$ul->addChild(html('li', $link));	
			}
			return html('h2', $this->simpleName($serviceName)) . $ul;
		}
		
		/**
		*  Get a list of services
		*  @method getServicesList
		*  @private
		*  @return {String} HTML list of services
		*/
		private function getServicesList()
		{
			// Generate the services
			$ul = html('ul');
			if ($this->aliases)
			{
				foreach ($this->aliases as $alias=>$className)
				{
					$link = html('a', $this->simpleName($className));
					$link->href = $this->settings->basePath . $this->browserUri.'/'.$alias;
					if (in_array($alias, $this->termiteAliases))
					{
						$link->class = 'internal';
					}
					$ul->addChild(html('li', $link));
				}
			}
			return $ul;
		}
		
		/**
		*  Get the name of the class only
		*  @method simpleName
		*  @private
		*  @param {String} className The full name of the class with namespace 
		*  @return {String} The last class name
		*/
		private function simpleName($className)
		{
			return substr($className, strrpos($className, '\\')+1);
		}
		
		/**
		*  Display the arguments as a string
		*  @method displayArgs
		*  @param {Array} args The arguments array
		*  @return {String} The string representation of the arguments, comma-separated
		*/
		private function displayArgs($args)
		{
			$res = array();
			if ($args)
			{
				foreach($args as $i=>$val)
				{
					if ($val === null)
					{
						$res[$i] = 'null';
						continue;
					}
					
					if ($val === false)
					{
						$res[$i] = 'false';
						continue;
					}
					
					if ($val === true)
					{
						$res[$i] = 'true';
						continue;
					}
					
					$res[$i] = (is_array($args[$i])) ?
						'['.$this->displayArgs($val).']':
						(preg_match('/^[0-9\.]*$/', $val) ? 
							(int)$val : "'$val'");
				}
			}
			return $res ? implode(', ', $res) : '';
		}
	}
}