<?php

namespace Canteen\Controllers
{
	use Canteen\Errors\CanteenError;
	use Canteen\HTML5\SimpleList;
	use Canteen\Logger\Logger;
	use Canteen\Utilities\CanteenBase;
	use \Exception;

	/**
	*  Display a fatal Canteen error or exception
	*  @class ErrorController
	*  @extends CanteenBase
	*/
	class ErrorController extends CanteenBase
	{
		/*
		*  Set the error to display
		*  @method display
		*  @param {Exception} error The error thrown
		*/
		public function display(Exception $error)
		{
			if (!$error) return;

			$fatalError = ($error instanceof CanteenError) ? 
				$error->getResult():
				CanteenError::convertToResult($error);

			$debug = $this->settings->exists('debug') ? 
				$this->settings->debug : true;
			
			$async = $this->settings->exists('asyncRequest') ? 
				$this->settings->asyncRequest : false;
			
			$data = [
				'type' => 'fatalError',
				'debug' => $debug
			];
			
			$data = array_merge($fatalError, $data);
			
			if (!$debug) 
			{
				unset($data['stackTrace']);
				unset($data['file']);
			}

			if ($async)
			{
				echo json_encode($data);
			}
			else
			{
				if ($debug)
				{
					$data['stackTrace'] = new SimpleList($data['stackTrace'], null, 'ol');
				}
				$result = $this->parser->template('FatalError', $data);
				$this->parser->removeEmpties($result);

				$debugger = '';
			
				// The profiler
				if ($this->profiler)
				{
					$debugger .= $this->profiler->render();
				}
				// The logger
				if ($debug && class_exists('Canteen\Logger\Logger') && Logger::instance())
				{
					$debugger .= Logger::instance()->render();
				}
				// If there are any debug trace or profiler
				if ($debugger)
				{
					$result = str_replace('</body>', $debugger . '</body>', $result);
				}
				echo $result;
			}
			exit;
		}
	}
}