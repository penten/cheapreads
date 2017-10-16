<?php

function parseDate($dt) {
	if($dt) return new DateTime($dt);
	return false;
}

function getShelf($shelf) {
	$key = $_SERVER['GOODREADS_KEY'];
	$uid = $_SERVER['GOODREADS_UID'];

	$page = 1;
	$per_page = 100;
	$books = [];
	$total = 0;

	$url = "https://www.goodreads.com/review/list/$uid.xml?key=$key&v=2&shelf=$shelf&per_page=$per_page";

	do {
		$xmlstr = file_get_contents("$url&page=$page");
		$xml = new SimpleXMLElement($xmlstr);
		# $total = $xml->reviews->attributes()->total;
	
		foreach($xml->reviews->review as $book) {
			$id = (string)$book->book->id;

			$books[$id] = [
				"goodreads_id" => $id,
				"my_rating" => (string)$book->rating,
				"read_dt" => parseDate((string)$book->read_at),
				"started_dt" => parseDate((string)$book->started_at),
				"title" => (string)$book->book->title,
				"image" => (string)$book->book->small_image_url,
				"isbn" => (string)$book->book->isbn,
				"isbn13" => (string)$book->book->isbn13,
				"rating" => (string)$book->book->average_rating,
				"author" => (string)$book->book->authors->author->name
			];
		}
		$page++;
	} while(count($xml->reviews->review));

	uasort($books, function($ba, $bb) { 
		$a = $ba['read_dt']; $b = $bb['read_dt'];
	       	return $a == $b ? 0 : ($a < $b ? 1 : -1); 
	});
	return $books;
}

/*
$books = getShelf('read');
foreach($books as $book) {
	//	if($book['goodreads_id'] == '22814814') { print_r($book); }
	print $book['title'] . "\n";
}
 */
