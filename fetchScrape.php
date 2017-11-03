<?php

include 'vendor/autoload.php';
include 'goodreads.php';

use Goutte\Client;

$to = $argv[1];

// todo, store historical so we can only send where it has changed

function findKindlePrice($client, $form, $isbn, $title) {
	$r = false;
	if($isbn) {
		$url = "https://www.amazon.co.uk/whatever/dp/$isbn";
		$r = extractKindlePrice($client, $url);
	}

	// could not find this isbn, or the kindle price
	// try searching by name in case we can find the book under a different edition
	if(!$r || @$r['price'] == 999999) {
		try {
			$url = findKindleProductPage($client, $form, $title);
			$r = extractKindlePrice($client, $url);
		} catch (Exception $e) {
			print "Unable to extract kindle URL from search results: probably captcherd";
		}
	}

	return $r;
}

function findKindleProductPage($client, $form, $title) {
	$form['field-keywords'] = $title;
	$crawler = $client->submit($form);
	return $crawler->filter('.s-access-detail-page')->attr('href');
}

function extractKindlePrice($client, $page) {
	$crawler = $client->request('GET', $page);
	
	try {
		$title = $crawler->filter("#productTitle,#ebooksProductTitle")->text(); // todo: check against expected
		$canonical = $crawler->filter('link[rel="canonical"]')->attr('href');
	} catch (\Exception $e) {
		// this is not a valid product page
		return false;
	}

	$prices = $crawler->filterXPath("//span[./text()='Kindle Edition']/following-sibling::span")->each(function ($node) {
		$txt = $node->text();
		if(strpos($txt, "£") !== false) {
			return floatval(str_replace('£', '', trim($txt)));
		}
	});

	$price = false;
	foreach($prices as $p) { 
		if(!empty($p)) { $price = $p; break; } 
	}

	if(!$price) { 
		// todo: no kindle version maybe, should give book price?
		return ["title" => $title, "price" => 999999, "uri" => $canonical];
	}


	return ["title" => $title, "price" => $price, "uri" => $canonical];
}

$client = new Client();
$form = $client->request('GET', 'https://www.amazon.co.uk/Kindle-eBooks-books/b/ref=sv_kinc_1?ie=UTF8&node=341689031')
		->evaluate("//form[contains(@class, 'nav-searchbar')]")->form();

$data = [];

foreach(getShelf('to-read') as $book) {
	$row = [
		"gr_title" => $book['title'],
		"gr_link" => "https://www.goodreads.com/book/show/" . $book['goodreads_id'], 
		"gr_image" => $book['image'],
		"gr_author" => $book['author'],
		"gr_rating" => $book['rating'],
	];

	$price = findKindlePrice($client, $form, $book['isbn'], $book['title']);
	if($price) {
		$row["kindle_price"] = $price['price'];
		$row["kindle_title"] = $price['title'];
		$row["kindle_uri"] = $price['uri'];
		print "Price found for " . $price['title'] . " -> " . $price['price'] . "\n";
		sleep(3); // try to avoid being classed as a bot
	} else {
		print "No price found for " . $book['title'] . " -- captcha?\n";
		break;
	}

	$data[] = $row;
}

function cmp($a, $b) {
	$na = isset($a['kindle_price']) ? $a['kindle_price'] : 99999;
	$nb = isset($b['kindle_price']) ? $b['kindle_price'] : 99999;
	if($na == $nb) return 0;
	return ($na < $nb) ? -1 : 1;
}

usort($data, 'cmp');

// create email
$intro = "Todo: store prices and price-drops at the top<br/>Todo: include a random quote from book quote files<br/>Todo: pick up false matches, maybe include author name in search?";
$html = "<html><body>$intro<table>";


foreach($data as $row) {
	$img = '<img src="' . $row['gr_image'] . '" />';
	if(isset($row['kindle_price'])) {
		if($row['kindle_price'] == 999999) {
			$txt = '<b>No kindle version found</b>';
		} else {
			$txt = '<b>£' . $row['kindle_price'] . '</b>';
		}
		$txt .= '<br/><a href="' .  $row['kindle_uri'] . '">' . $row['kindle_title'] . '</a><br/>';
		$txt .= '(' . $row['gr_title'] . ')<br/>';
	} else {
		$txt = "<b>Could not find price for this book</b><br/>" . $row['gr_title'] . '<br/>';
	}
	$txt .= "by ${row['gr_author']}, with ${row['gr_rating']} stars on <a href=\"". $row['gr_link'] ."\">goodreads</a>";
	$html .= "<tr><td>$img</td><td style=\"padding:10px\">$txt<br/></td></tr>";
}

$html .= "</table></html>";

$boundary = md5(uniqid());
$headers = "From: Max <max@maxjmartin.com>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n";

$body = "--$boundary\r\n" .
"Content-Type: text/html; charset=UTF-8\r\n" .
"Content-Transfer-Encoding: base64\r\n\r\n";
$body .= chunk_split( base64_encode( $html ) );
$body .= "--$boundary--";

$subject = 'Cheapreads ' . date('Y-m-d') . '!';
mail($to, $subject, $body, $headers);
