<?php

/**
 * Test: Nette\Database\Table: RelatedMany().
 *
 * @author     Jakub Vrana
 * @author     Jan Skrasek
 * @author     Jachym Tousek
 * @package    Nette\Database
 * @subpackage UnitTests
 * @multiple   databases.ini
 */

require __DIR__ . '/connect.inc.php'; // create $connection

Nette\Database\Helpers::loadFromFile($connection, __DIR__ . "/{$driverName}-nette_test1.sql");



$tags1 = $tags2 = array();

foreach ($connection->table('book') as $book) { // SELECT * FROM `book`
	foreach ($book->relatedMany('book_tag:tag') as $tag) { // SELECT * FROM `book_tag` WHERE (`book_tag`.`book_id` IN (1, 2, 3, 4)) ; SELECT * FROM `tag` WHERE (`tag`.`id` IN (21, 22, 23, 25, 26))
		$tags1[$book->title][] = $tag->name;
	}

	foreach ($book->relatedMany('book_tag', 'tag') as $tag) {
		$tags2[$book->title][] = $tag->name;
	}
}

$expectTags = array(
	'1001 tipu a triku pro PHP' => array('PHP', 'MySQL'),
	'JUSH' => array('JavaScript'),
	'Nette' => array('PHP'),
	'Dibi' => array('PHP', 'MySQL'),
);

Assert::same($expectTags, $tags1);
Assert::same($expectTags, $tags2);



$connection->table('book')->get(1)->relatedMany('book_tag', 'tag')->insert(array(
	array('name' => 'tipy'),
	array('name' => 'triky'),
)); // INSERT INTO `tag` (`name`) VALUES ('tipy'), ('triky')

$connection->table('tag')->get(23)->related('book_tag_alt')->delete(); // prevent integrity constraint violation

$delete = $connection->table('book')->get(2)->relatedMany('book_tag', 'tag')->delete(); // DELETE FROM `book_tag` WHERE (`book_id` = 2) AND (`tag_id` IN (23)); DELETE FROM `tag` WHERE (`id` IN (23))

Assert::same(1, $delete);



$tags1 = array();

foreach ($connection->table('book') as $book) { // SELECT * FROM `book`
	foreach ($book->relatedMany('book_tag:tag') as $tag) { // SELECT * FROM `book_tag` WHERE (`book_tag`.`book_id` IN (1, 2, 3, 4)) ; SELECT * FROM `tag` WHERE (`tag`.`id` IN (21, 22, 23, 25, 26))
		$tags1[$book->title][] = $tag->name;
	}
}

$expectTags = array(
	'1001 tipu a triku pro PHP' => array('PHP', 'MySQL', 'tipy', 'triky'),
	'Nette' => array('PHP'),
	'Dibi' => array('PHP', 'MySQL'),
);

Assert::same($expectTags, $tags1);
