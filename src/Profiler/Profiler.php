<?php

/**
*  Original library from:
*  @link http://github.com/jimrubenstein/php-profiler
*  @author Jim Rubenstein <jrubenstein@gmail.com>
*/

/**
*  @module Canteen\Profiler
*/
namespace Canteen\Profiler
{
	use Canteen\Parser\Parser;
	use \Exception;
	
	/**
	*  The Profiler is used to analyze your application in order to determine where you could use
	*  the most optimization. The profiler class is where all interaction with the Profiler takes 
	*  place. You use it to create step nodes and render the output.
	*  @class Profiler
	*/
	class Profiler
	{		
		/**
		*  Used to insure that the {@link init} method is only called once.
		*  @property {Boolean} init
		*  @protected
		*  @static
		*/
		protected static $init = false;

		/**
		*  Used to identify when the profiler has been enabled. If <em>false</em> no 
		*  profiling data is stored, in order to reduce the overhead of running the profiler
		*  @property {Boolean} enabled
		*  @static
		*  @protected
		*/
		protected static $enabled = false;

		/**
		*  Tracks the current step node.
		*  @property {ProfilerNode} currentNode
		*  @protected
		*  @static
		*/
		protected static $currentNode = null;

		/**
		*  Tracks the current SQL note
		*  @property {ProfilerSQLNode} sqlProfile
		*  @protected
		*  @static
		*/
		protected static $sqlProfile = null;

		/**
		*  Tracks the current tree depth
		*  @property {int} depthCount
		*  @protected
		*  @static
		*/
		protected static $depthCount = 0;

		/**
		*  List of all top-level step nodes
		*  @property {Array} topNodes
		*  @protected
		*  @static
		*/	
		protected static $topNodes = array();

		/**
		*  Time the profiler was included. This is used to calculate 
		*  time-from-start values for all methods as well as total running time.
		*  @property {Number} globalStart
		*  @protected
		*  @static
		*/	
		protected static $globalStart = 0;

		/**
		*  Time the profiler 'ends'. This is populated just before rendering 
		*  output (see {@link Profiler::render()})
		*  @property {Number} globalEnd
		*  @protected
		*  @static
		*/
		protected static $globalEnd = 0;

		/**
		*  Total time script took to run
		*  @property {Number} globalDuration
		*  @protected
		*  @static
		*/
		protected static $globalDuration = 0;

		/**
		*  Global tracker for step times. Keeps track of how long each node 
		*  took to execute.  This is used to determine
		*  what is a "trivial" node, and what is not.
		*  @property {Array} childDurations
		*  @protected
		*  @static  
		*/	
		protected static $childDurations = array();

		/**
		*  Percentile boundary for trivial execution times
		*  @property {Number} trivialThreshold
		*  @protected
		*  @static
		*/	
		protected static $trivialThreshold = .75;

		/**
		*  Execution time cut off value for trivial/non-trivial nodes
		*  @property {Number} trivialThresholdMS
		*  @protected
		*  @static
		*/
		protected static $trivialThresholdMS = 0;

		/**
		*  Total amount of time used in SQL queries
		*  @property {Number} totalQueryTime
		*  @protected
		*  @static
		*/
		protected static $totalQueryTime = 0;

		/**
		*  Used to identify when some methods are accessed internally
		*  versus when they're used externally (as an api or so)
		*  @property {String} profilerKey
		*  @protected
		*  @static
		*/	
		protected static $profilerKey = null;

		/**
		*  A lightweight shell node used to return when the profiler is disabled.
		*  @property {ProfilerGhostNode} ghostNode
		*  @protected
		*  @static
		*/	
		protected static $ghostNode;

		/**
		*  Create a constructor that basically says "don't construct me!"
		*/
		public function __construct()
		{
			throw new Exception("The Profiler class is a static class. Do not instantiate it, access all member methods statically.");
		}

		/**
		*  Initialize the profiler
		*  @method init
		*  @static
		*  @return null doesn't return anything.
		*/
		public static function init()
		{
			if (self::$init) return;

			self::$globalStart = microtime(true);
			self::$profilerKey = md5(rand(1,1000) . 'louddoor!' . time());
			self::$ghostNode = new ProfilerGhostNode;
			self::$init = true;
		}

		/**
		*  Check to see if the profiler is enabled 
		*  @method isEnabled
		*  @static
		*  @return {Boolean} True if profiler is enabled, false if disabled
		*/
		public static function isEnabled()
		{
			return self::$enabled;
		}

		/**
		*  Enable the profiler
		*  @method enable
		*  @static
		*/
		public static function enable()
		{
			self::$enabled = true;
		}

		/**
		*  Disable the profiler
		*  @method disable
		*  @static
		*/
		public static function disable()
		{
			if (self::$currentNode == null && count(self::$topNodes) == 0)
			{
				self::$enabled = false;
			}
			else
			{
				throw new exception("Can not disable profiling once it has begun.");
			}
		}

		/**
		*  Start a new step. This is the most-called method of the profiler.  
		*  It initializes and returns a new step node.
		*  @method start
		*  @static
		*  @param {Sstring} nodeName name/identifier for your step. is 
		*   used later in the output to identify this step
		*  @return {ProfilerNode|ProfilerGhostNode} returns an instance of 
		*  	a {@link ProfilerNode} if the profiler is enabled, or 
		*   a {@link ProfilerGhostNode} if it's disabled
		*/	
		public static function start($nodeName)
		{	
			if (!self::isEnabled()) return self::$ghostNode;

			$newNode = new ProfilerNode($nodeName, ++self::$depthCount, self::$currentNode, self::$profilerKey);

			if (self::$currentNode)
			{
				self::$currentNode->addChild($newNode);
			}
			else
			{
				self::$topNodes []= $newNode;
			}

			self::$currentNode = $newNode;

			return self::$currentNode;
		}

		/**
		*  End a step by name, or end all steps in the current tree.
		*  @method end
		*  @static
		*  @param {String} nodeName ends the first-found step with this name. (Note: a warning is generated if it's not the current step, because this is probably unintentional!)
		*  @param {Boolean} nuke denotes whether you are intentionally attempting to terminate the entire step-stack.  If true, the warning mentioned is not generated.
		*  @return {Boolean}|ProfilerNode|ProfilerGhostNode returns null if you ended the top-level step node, or the parent to the ended node, or a ghost node if the profiler is disabled.
		*/
		public static function end($nodeName, $nuke = false)
		{	
			if (!self::isEnabled()) return self::$ghostNode;

			if (self::$currentNode == null)
			{
				return;
			}

			while (self::$currentNode && self::$currentNode->getName() != $nodeName)
			{
				if (!$nuke)
				{
					trigger_error("Ending profile node '" . self::$currentNode->getName() . "' out of order (Requested end: '{$nodeName}')", E_USER_WARNING);
				}

				self::$currentNode = self::$currentNode->end(self::$profilerKey);
				self::$depthCount --;
			}

			if (self::$currentNode && self::$currentNode->getName() == $nodeName)
			{
				self::$currentNode = self::$currentNode->end(self::$profilerKey);
				self::$depthCount --;
			}

			return self::$currentNode;
		}

		/**
		*  Start a new sql query
		*
		*  This method is used to tell the profiler to track an sql query.  These are treated differently than step nodes
		*  @method sqlStart
		*  @static
		*  @param {String} query the query that you are running (used in the output of the profiler so you can view the query run)
		*  @return {ProfilerSQLNode|ProfilerGhostNode} returns an instance of the {@link ProfilerGhostNode} if profiler is enabled, or {@link ProfilerGhostNode} if disabled
		*/
		public static function sqlStart($query)
		{	
			if (!self::isEnabled()) return self::$ghostNode;

			if (!self::$currentNode)
			{
				self::start("Profiler Default Top Level");			
			}

			self::$sqlProfile = new ProfilerSQLNode($query, self::$currentNode);

			self::$currentNode->sqlStart(self::$sqlProfile);

			return self::$sqlProfile;
		}

		/**
		*  Stop profiling the current SQL call
		*  @method sqlEnd
		*  @static
		*/
		public static function sqlEnd()
		{
			if (!self::$sqlProfile) return;

			self::$sqlProfile->end();
		}

		/**
		*  Increment the total query time
		*
		*  This method is used by the {@link ProfilerGhostNode} to increment the total query time for the page execution.
		*  This method should <b>never</b> be called in userland.  There is zero need to.
		*  @method addQueryDuration
		*  @static
		*  @param {Number} time amount of time the query took to execute in microseconds.
		*  @return {Number} Current amount of time (in microseconds) used to execute sql queries.
		*/
		public static function addQueryDuration($time)
		{
			return self::$totalQueryTime += $time;
		}

		/**
		*  Get the total amount of query time
		*  @method getTotalQueryTime
		*  @static
		*  @return {Number} Total time used to execute sql queries (milliseconds, 1 significant digit)
		*/
		public static function getTotalQueryTime()
		{
			return round(self::$totalQueryTime*  1000, 1);
		}

		/**
		*  Get the global start time
		*  @method getGlobalStart
		*  @static
		*  @return {Number} Start time of the script from unix epoch (milliseconds, 1 significant digit)
		*/
		public static function getGlobalStart()
		{
			return round(self::$globalStart*  1000, 1);
		}

		/**
		*  Get the global script duration
		*  @method getGlobalDuration
		*  @static
		*  @return {Number} Duration of the script (in milliseconds, 1 significant digit)
		*/
		public static function getGlobalDuration()
		{
			return round(self::$globalDuration*  1000, 1);
		}

		/**
		*  Get the global memory usage in KB
		*  @method getMemUsage
		*  @static
		*  @param {String} [unit=''] a metric prefix to force the unit of bytes used (B, K, M, G)
		*
		*/
		public static function getMemUsage($unit = '')
		{
			$usage = memory_get_usage();

			if ($usage < 1e3 || $unit == 'B')
			{
				$unit = '';
			}
			elseif ($usage < 9e5 || $unit == 'K')
			{
				$usage = round($usage / 1e3, 2);
				$unit = 'K';
			}
			elseif ($usage < 9e8 || $unit == 'M')
			{
				$usage = round($usage / 1e6, 2);
				$unit = 'M';
			}
			elseif ($usage < 9e11 || $unit = 'G')
			{
				$usage = round($usage / 1e9, 2);
				$unit = 'G';
			}
			else
			{
				$usage = round($usage / 1e12, 2);
				$unit = 'T';
			}

			return array(
				'num' => $usage,
				'unit' => $unit,
			);
		}

		/**
		*  Render the profiler output
		*  @method render
		*  @static
		*  @param {int} [showDepth=-1] the depth of the step tree to traverse when rendering the profiler output. -1 to render the entire tree
		*/
		public static function render($showDepth = -1)
		{	
			if (!self::isEnabled()) return self::$ghostNode;

			self::end("___GLOBAL_END_PROFILER___", true);

			self::$globalEnd = microtime(true);
			self::$globalDuration = self::$globalEnd - self::$globalStart;

			self::calculateThreshold();

			$mem = self::getMemUsage();
			list($serverName) = explode('.', php_uname('n'));
			$duration = self::getGlobalDuration();

			$nodes = '';
			$queryNodes = '';
			$res = '';

			foreach(self::$topNodes as $node)
			{
				$nodes .= ProfilerRenderer::renderNode($node, $showDepth);
				$queryNodes .= ProfilerRenderer::renderNodeSQL($node);
			}

			$res .= Parser::getTemplate(
				'Profiler',
				array(
					'globalDuration' => $duration,
					'memUsage' => $mem['num'], 
					'memUnit' => $mem['unit'],
					'serverName' => $serverName,
					'title' => substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '?')),
					'date' => date('D, d M Y H:i:s T'),
					'queryPercent' => $duration > 0 ? round(self::getTotalQueryTime() / $duration, 2)*  100 : 0,
					'nodes' => $nodes,
					'queryNodes' => $queryNodes
				)
			);

			return $res;
		}

		/**
		*  Add node duration to the {@link Profiler::$childDurations} variable
		*  @method addDuration
		*  @static
		*  @param {Number} time duration of the child node in microseconds
		*/
		public static function addDuration($time)
		{
			self::$childDurations []= $time;
		}

		/**
		*  Set the Percentile Boundary Threshold. 
		*  This is used to set the percentile boundary for when a node is considered trivial or not.
		*  By default, .75 is used.  This translates to the fastest 25% of nodes being regarded "trivial".
		*  This is a sliding scale, so you will always see some output, regardless of how fast your application runs.
		*  @method setTrivialThreshold
		*  @static
		*  @param {Number} threshold the threshold to use as the percentile boundary
		*/
		static public function setTrivialThreshold($threshold)
		{
			self::$trivialThreshold = $threshold;
		}

		/**
		*  Calculate the time cut-off for a trivial step. 
		*  Utilizes the {@link Profiler::$trivialThreshold} value to determine how fast a step must be to be regarded "trivial"
		*  @method calculateThreshold
		*  @static
		*  @protected
		*/
		protected static function calculateThreshold()
		{
			if (count(self::$childDurations))
			{
				foreach (self::$childDurations as &$childDuration)
				{
					$childDuration = round($childDuration*  1000, 1);
				}

				sort(self::$childDurations);

				self::$trivialThresholdMS = self::$childDurations[ floor(count(self::$childDurations)*  self::$trivialThreshold) ];
			}
		}

		/**
		*  Determines if a node is trivial
		*  @method isTrivial
		*  @static
		*  @param {ProfilerNode} node The node to investigate
		*  @return {Boolean} True if a node is trivial, false if not
		*/
		public static function isTrivial($node)
		{
			$node_duration = $node->getSelfDuration();

			return $node_duration < self::$trivialThresholdMS;
		}
	}

	/**
	*  Initialize the profiler as soon as it's available, 
	*  so we can get an accurate start-time and duration.
	*/
	Profiler::init();
}