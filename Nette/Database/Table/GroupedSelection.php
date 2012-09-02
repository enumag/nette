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
 * Representation of filtered table grouped by some column.
 * GroupedSelection is based on the great library NotORM http://www.notorm.com written by Jakub Vrana.
 *
 * @author     Jakub Vrana
 * @author     Jan Skrasek
 * @author     Jan Dolecek
 */
class GroupedSelection extends AbstractGroupedSelection
{
	/** @var string grouping column name */
	protected $column;



	/**
	 * Creates filtered and grouped table representation.
	 * @param  Selection  $refTable
	 * @param  string  database table name
	 * @param  string  joining column
	 */
	public function __construct(Selection $refTable, $table, $column)
	{
		parent::__construct($refTable, $table);
		$this->column = $column;
	}



	/********************* sql selectors ****************d*g**/



	public function select($columns)
	{
		if (!$this->sqlBuilder->getSelect()) {
			$this->sqlBuilder->addSelect("$this->name.$this->column");
		}

		return parent::select($columns);
	}



	public function order($columns)
	{
		if (!$this->sqlBuilder->getOrder()) {
			// improve index utilization
			$this->sqlBuilder->addOrder("$this->name.$this->column" . (preg_match('~\\bDESC$~i', $columns) ? ' DESC' : ''));
		}

		return parent::order($columns);
	}



	/********************* aggregations ****************d*g**/



	protected function calculateAggregation($function)
	{
		$selection = $this->createSelectionInstance();
		$selection->getSqlBuilder()->importConditions($this->getSqlBuilder());
		$selection->select($function);
		$selection->select("$this->name.$this->column");
		$selection->group("$this->name.$this->column");

		$aggregation = array();
		foreach ($selection as $row) {
			$aggregation[$row[$this->column]] = $row;
		}
		return $aggregation;
	}



	/********************* internal ****************d*g**/



	protected function doMapping(&$rows, &$output)
	{
		$limit = $this->sqlBuilder->getLimit();

		$offset = array();
		foreach ($rows as $key => $row) {
			$ref = & $output[$row[$this->column]];
			$skip = & $offset[$row[$this->column]];
			if ($limit === NULL || $rows <= 1 || (count($ref) < $limit && $skip >= $this->sqlBuilder->getOffset())) {
				$ref[$key] = $row;
			} else {
				unset($rows[$key]);
			}
			$skip++;
			unset($ref, $skip);
		}
	}



	/********************* manipulation ****************d*g**/



	public function insert($data)
	{
		if ($data instanceof \Traversable && !$data instanceof Selection) {
			$data = iterator_to_array($data);
		}

		if (Nette\Utils\Validators::isList($data)) {
			foreach (array_keys($data) as $key) {
				$data[$key][$this->column] = $this->active;
			}
		} else {
			$data[$this->column] = $this->active;
		}

		return parent::insert($data);
	}



	public function update($data)
	{
		$builder = $this->sqlBuilder;

		$this->sqlBuilder = new SqlBuilder($this);
		$this->where($this->column, $this->active);
		$return = parent::update($data);

		$this->sqlBuilder = $builder;
		return $return;
	}



	public function delete()
	{
		$builder = $this->sqlBuilder;

		$this->sqlBuilder = new SqlBuilder($this);
		$this->where($this->column, $this->active);
		$return = parent::delete();

		$this->sqlBuilder = $builder;
		return $return;
	}

}
