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
 * Abstract base - Representation of filtered table grouped by some column.
 * GroupedSelection is based on the great library NotORM http://www.notorm.com written by Jakub Vrana.
 *
 * @author     Jakub Vrana
 * @author     Jan Skrasek
 * @author     Jan Dolecek
 */
abstract class AbstractGroupedSelection extends Selection
{
	/** @var Selection referenced table */
	protected $refTable;

	/** @var int primary key */
	protected $active;



	/**
	 * Creates filtered and grouped table representation.
	 * @param  Selection  $refTable
	 * @param  string  database table name
	 */
	public function __construct(Selection $refTable, $table)
	{
		parent::__construct($refTable->connection, $table);
		$this->refTable = $refTable;
	}


	/**
	 * Sets active group
	 * @internal
	 * @param  int  primary key of grouped rows
	 * @return GroupedSelection
	 */
	public function setActive($active)
	{
		$this->active = $active;
		return $this;
	}



	/********************* aggregations ****************d*g**/



	public function aggregation($function)
	{
		$aggregation = & $this->getRefTable($refPath)->aggregation[$refPath . $function . $this->sqlBuilder->buildSelectQuery() . json_encode($this->sqlBuilder->getParameters())];

		if ($aggregation === NULL) {
			$aggregation = $this->calculateAggregation($function);
		}

		if (isset($aggregation[$this->active])) {
			foreach ($aggregation[$this->active] as $val) {
				return $val;
			}
		}
	}



	/**
	 * Calculates grouped aggregation, which is then stored and reused
	 * @param  string
	 * @return array aggregation-key -> scalar value
	 */
	abstract protected function calculateAggregation($function);



	public function count($column = NULL)
	{
		$return = parent::count($column);
		return isset($return) ? $return : 0;
	}



	/********************* internal ****************d*g**/



	protected function execute()
	{
		if ($this->rows !== NULL) {
			return;
		}

		$hash = md5($this->sqlBuilder->buildSelectQuery() . json_encode($this->sqlBuilder->getParameters()));

		$referencing = & $this->getRefTable($refPath)->referencing[$refPath . $hash];
		$this->rows = & $referencing['rows'];
		$this->referenced = & $referencing['refs'];
		$this->accessed = & $referencing['accessed'];
		$refData = & $referencing['data'];

		if ($refData === NULL) {
			$limit = $this->sqlBuilder->getLimit();
			$rows = count($this->refTable->rows);
			if ($limit && $rows > 1) {
				$this->sqlBuilder->setLimit(NULL, NULL);
			}
			parent::execute();
			$this->sqlBuilder->setLimit($limit, NULL);
			$refData = array();
			$this->doMapping($this->rows, $refData);
		}

		$this->data = & $refData[$this->active];
		if ($this->data === NULL) {
			$this->data = array();
		} else {
			foreach ($this->data as $row) {
				$row->setTable($this); // injects correct parent GroupedSelection
			}
			reset($this->data);
		}
	}



	/**
	 * Index fetched rows into a grouped structure
	 *
	 * @param  $rows rows from database
	 * @param  $output grouped result
	 */
	abstract protected function doMapping(&$rows, &$output);



	protected function getRefTable(& $refPath)
	{
		$refObj = $this->refTable;
		$refPath = $this->name . '.';
		while ($refObj instanceof AbstractGroupedSelection) {
			$refPath .= $refObj->name . '.';
			$refObj = $refObj->refTable;
		}

		return $refObj;
	}

}
