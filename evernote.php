<?php

require 'vendor/autoload.php';

function noteToText($body) {
	// Stip HTML from the note, replacing with sensible line breaks
	$raw_text = str_replace(['<br />', '</div>'], "\n", $body);
	$raw_text = str_replace("\r", "", $raw_text);
	$raw_text = str_replace("\n\n", "\n", $raw_text);
	$raw_text = str_replace("&nbsp;", " ", $raw_text);
	$raw_text = html_entity_decode($raw_text, ENT_QUOTES | ENT_XML1, 'UTF-8');
	return strip_tags($raw_text);
}

function parseNote($body) {
	// strip evernote specific tags (first 2 lines)
	$body = end(explode("\r\n", $body, 3));

	// strip en-note tags
	$body = str_replace('<en-note>', '', $body);
	return str_replace('</en-note>', '', $body);
}

function getTag($tag) {
	$token = $_SERVER['EVERNOTE_TOKEN'];

	$client = new \Evernote\Client($token, false);

	$search = new \Evernote\Model\Search("tag:$tag");
	$notebook = null;
	$scope = \Evernote\Client::SEARCH_SCOPE_DEFAULT;
	$order = \Evernote\Client::SORT_ORDER_TITLE;

	$results = $client->findNotesWithSearch($search, $notebook, $scope, $order, 200);
	$notes = [];
	foreach($results as $result) {
		$note = $client->getNote($result->guid);
		$notes[] = [
			"title" => $note->getTitle(),
			"body" => parseNote($note->getContent())
		];
	}

	return $notes;
}

// getTag('book');
