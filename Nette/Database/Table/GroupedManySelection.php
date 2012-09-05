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
	const SOURCE_COLUMN = '_nette_relation_source';

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
	public function __construct(Selection $refTable, $joinTable, $joinColumnSource, $targetTable, $joinColumnTarget)
	{
		parent::__construct($refTable, $targetTable);
		$this->joinTable = $joinTable;
		$this->joinColumnSource = $joinColumnSource;
		$this->joinColumnTarget = $joinColumnTarget;
	}



	/********************* aggregations ****************d*g**/



	protected function calculateAggregation($function)
	{
		$selection = $this->createSelectionInstance();
		$selection->getSqlBuilder()->importConditions($this->getSqlBuilder());
		$selection->select($function);
		$selection->select("$this->joinTable:$this->joinColumnSource AS " . self::SOURCE_COLUMN);
		$selection->group("$this->joinTable:$this->joinColumnSource");
		$selection->where("$this->joinTable:$this->joinColumnSource", $this->refTable->getKeys() ?: array($this->active));

		$aggregation = array();
		foreach ($selection as $row) {
			$aggregation[$row[self::SOURCE_COLUMN]] = $row;
		}
		return $aggregation;
	}



	/********************* internal ****************d*g**/



	protected function relatedKeys() {
		$active = $this->active;
		// TODO: 5.2 compatibility
		return array_keys(array_filter($this->mapping, function ($sourceKeys) use ($active) {
			return in_array($active, $sourceKeys);
		}));
	}



	protected function execute()
	{
		$builder = clone $this->sqlBuilder;
		$this->where("$this->joinTable:$this->joinColumnSource", $this->refTable->getKeys() ?: array($this->active));

		// TODO: nerespektuje getPreviousAccessed
		if (!$this->sqlBuilder->getSelect()) {
			$this->sqlBuilder->addSelect("$this->name.*");
		}
		$this->sqlBuilder->addSelect("$this->joinTable:$this->joinColumnSource AS " . self::SOURCE_COLUMN);

		parent::execute();
		$this->sqlBuilder = $builder;
	}



	protected function doMapping(&$rows, &$output)
	{
		$limit = $this->sqlBuilder->getLimit();

		$offset = array();
		foreach ($rows as $key => $row) {
			$id = $row[$this->primary];
			foreach ($this->mapping[$id] as $sourceId) {
				$ref = & $output[$sourceId];
				$skip = & $offset[$sourceId];
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



	protected function createRow(array $row)
	{
		// TODO: nemělo createRow by sloužit jen jako továrna?
		if (isset($row[$this->primary])) {
			$this->mapping[$row[$this->primary]][] = isset($row[self::SOURCE_COLUMN]) ? $row[self::SOURCE_COLUMN] : $this->active;
		}
		return parent::createRow($row);
	}



	/********************* manipulation ****************d*g**/



	public function insert($data)
	{
		$return = parent::insert($data);

		$selection = $this->createSelectionInstance();
		$selection->select($this->primary)->where($this->primary . ' >= ?', $this->connection->lastInsertId())->rewind();
		$insert = array();
		foreach ($selection->getKeys() as $id) {
			$insert[] = array($this->joinColumnSource => $this->active, $this->joinColumnTarget => $id);
		}
		$this->connection->table($this->joinTable)->insert($insert);

		return $return;
	}



	public function update($data)
	{
		$this->execute();
		$builder = $this->sqlBuilder;

		$this->sqlBuilder = new SqlBuilder($this);
		$this->where($this->primary, $this->relatedKeys());
		$return = parent::update($data);

		$this->sqlBuilder = $builder;
		return $return;
	}



	public function delete()
	{
		$this->execute();
		$this->connection->table($this->joinTable)
			->where($this->joinColumnSource, $this->active)
			->where($this->joinColumnTarget, array_keys($this->mapping))
			->delete();

		$builder = $this->sqlBuilder;

		$this->sqlBuilder = new SqlBuilder($this);
		$this->where($this->primary, $this->relatedKeys());
		$return = parent::delete();

		$this->sqlBuilder = $builder;
		return $return;
	}

}
