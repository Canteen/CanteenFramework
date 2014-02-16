<?php

/**
*  @module Canteen\Utilities
*/
namespace Canteen\Utilities
{
	/**
	*  An empty profiler to use if the Profiler isn't included
	*  @class EmptyProfiler
	*/
	class EmptyProfiler
	{
		public function __construct($parser=null){}

		public function start($nodeName){}

		public function end($nodeName, $nuke = false){}

		public function sqlStart($query){}

		public function sqlEnd(){}

		public function render($showDepth = -1){ return ''; }
	}
}