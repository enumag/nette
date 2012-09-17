<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 *
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Nette\Database\Table;

use Nette;



/**
 * Grouped M:N selection
 *
 * @author     Jan Dolecek
 * @author     Jachym Tousek
 */
class GroupedManySelection extends AbstractGroupedSelection
{
	/** @var array targetId -> sourceId[] */
	protected $mapping;

	/** @var string */
	protected $joinTable;

	/** @var string */
	protected $joinColumnSource;

	/** @var string */
	protected $joinColumnTarget;



	/**
	 * Creates filtered and grouped table representation.
	 * @param  Selection  $refTable
	 * @param  string
	 * @param  string
	 * @param  string
	 * @param  string
	 * @param  array
	 */
	public function __construct(Selection $refTable, $joinTable, $joinColumnSource, $targetTable, $joinColumnTarget, array $mapping)
	{
		parent::__construct($refTable, $targetTable);
		$this->mapping = $mapping;
		$this->joinTable = $joinTable;
		$this->joinColumnSource = $joinColumnSource;
		$this->joinColumnTarget = $joinColumnTarget;
	}



	/********************* aggregations ****************d*g**/



	protected function calculateAggregation($function)
	{
		throw new \Nette\NotImplementedException;
	}



	/********************* internal ****************d*g**/



	protected function doMapping(&$rows, &$output)
	{
		$limit = $this->sqlBuilder->getLimit();

		$offset = array();
		foreach ($rows as $key => $row) {
			$iid = $row[$this->primary];
			foreach ($this->mapping[$iid] as $targetId) {
				$ref = & $output[$targetId];
				$skip = & $offset[$targetId];
				if ($limit === NULL || $rows <= 1 || (count($ref) < $limit && $skip >= $this->sqlBuilder->getOffset())) {
					$ref[$key] = $row;
				} else {
					unset($rows[$key]);
				}
				$skip++;
				unset($ref, $skip);
			}
		}
	}



	/********************* manipulation ****************d*g**/



	public function insert($data)
	{
		$return = parent::insert($data);

		$rows = $this->connection->table($this->name)->select($this->primary)->where($this->primary . ' >= ?', $this->connection->lastInsertId());
		$insert = array();
		foreach ($rows as $id => $_) {
			$insert[] = array($this->joinColumnSource => $this->active, $this->joinColumnTarget => $id);
		}
		$this->connection->table($this->joinTable)->insert($insert);

		return $return;
	}



	public function delete()
	{
		$this->rewind();

		$this->connection->table($this->joinTable)
			->where($this->joinColumnSource, $this->active)
			->where($this->joinColumnTarget, $this->keys)
			->delete();

		return parent::delete();
	}

}
