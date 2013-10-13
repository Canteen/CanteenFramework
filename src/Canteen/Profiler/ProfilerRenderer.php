<?php

/**
*  @module Canteen\Profiler
*/
namespace Canteen\Profiler
{
	use Canteen\Parser\Parser;
	
	/**
	*  Rendering class used to render special step nodes.
	*  @class ProfilerRenderer
	*/
	class ProfilerRenderer
	{	
		/**
		*  Render a {@link ProfilerNode} step node and it's children recursively
		*  @method renderNode
		*  @static
		*  @param {ProfilerNode} node The node to render
		*  @param {int} [maxDepth=-1] the maximum depth of the tree to traverse and render.  -1 to traverse entire tree
		*  @return {String} The HTML markup rendering of the profiler node
		*/
		public static function renderNode($node, $maxDepth = -1) 
		{ 
			$res = Parser::getTemplate(
				'ProfilerNode', 
				array(
					'depth' => $node->getDepth(),
					'trivial' => Profiler::isTrivial($node) && !$node->hasNonTrivialChildren() ? 'profiler-trivial' : '',
					'indent' => str_repeat('&nbsp;&nbsp;&nbsp;', $node->getDepth() - 1),
					'name' => $node->getName(),
					'selfDuration' => $node->getSelfDuration(),
					'totalDuration' => $node->getTotalDuration(),
					'startDelay' => round($node->getStart() - profiler::getGlobalStart(), 1),
					'id' => md5($node->getName() . $node->getStart()),
					'queryCount' => $node->getSQLQueryCount(),
					'queryTime' => $node->getTotalSQLQueryDuration()
				)
			);

			if ($node->hasChildren() && ($maxDepth == -1 || $maxDepth > $node->getDepth()))
			{
				foreach ($node->getChildren() as $childNode)
				{
					$res .= self::renderNode($childNode, $maxDepth);
				}
			}
			return $res;
		}

		/**
		*  Render all {@link ProfilerSQLNode} queries for the given node, and traverse it's child nodes
		*  to render their queries also.
		*  @method renderNodeSQL
		*  @static
		*  @param {ProfilerNode} node The node to begin rendering
		*  @return {String} The HTML markup rendering of the SQL node
		*/
		public static function renderNodeSQL($node)
		{
			$res = '';

			if ($node->hasSQLQueries())
			{
				$c = 0; //row counter
				$nodeQueries = $node->getSQLQueries();

				$id = md5($node->getName() . $node->getStart());

				$res .= Parser::getTemplate('ProfilerSQLHeader', array(
					'id' => $id,
					'name' => $node->getName()
				));

				foreach ($nodeQueries as $query)
				{
					$stack = array();
					foreach ($query->getCallstack() as $stackStep)
					{
						$stack[] = array(
							'rowClass' => ++$c % 2? 'odd' : 'even',
							'class' => !empty($stackStep['class'])? $stackStep['class'] . $stackStep['type'] : '',
							'function' => $stackStep['function']
						);
					}
					$res .= Parser::getTemplate(
						'ProfilerSQLNode', 
						array(
							'id' => $id,
							'startTimer' => round($query->getStart() - Profiler::getGlobalStart(), 1),
							'duration' => $query->getDuration(),
							'type' => $query->getQueryType(),
							'stack' => $stack,
							'queryId' => md5($query->getQuery()),
							'query' => $query->getQuery()
						)
					);
				}
			}

			if ($node->hasChildren())
			{
				foreach ($node->getChildren() as $childNode)
				{
					$res .= self::renderNodeSQL($childNode);
				}
			}
			return $res;
		}
	}
}