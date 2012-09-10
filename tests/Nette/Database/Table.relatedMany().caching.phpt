<?php

/**
 * Test: Nette\Database\Table: Shared relatedMany data caching.
 *
 * @author     Jan Skrasek
 * @author     Jachym Tousek
 * @package    Nette\Database
 * @subpackage UnitTests
 * @multiple   databases.ini
 */

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/{$driverName}-nette_test1.sql");



$books = $connection->table('book');
foreach ($books as $book) {
	foreach ($book->relatedMany('book_tag:tag') as $tag) {
		$tag->name;
	}
}

$tags = array();
foreach ($books as $book) {
	foreach ($book->relatedMany('book_tag_alt:tag') as $tag) {
		$tags[] = $tag->name;
	}
}

Assert::same(array(
	'PHP',
	'MySQL',
	'JavaScript',
	'Neon',
), $tags);
