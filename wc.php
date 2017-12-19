<?php

require 'evernote.php';

$wc = 0;
$tag = $argv[1];
$wc_fn = "/tmp/${tag}_evernote_wc";
$previous_wc = file_get_contents($wc_fn);

foreach(getTag($tag) as $scene) {
	// Count only the scenes in the book, exclude notes
	if(strpos($scene['title'], 'Scene') !== false) {
		$scene_wc = str_word_count(strip_tags($scene['body']));
		$wc += $scene_wc;
	}
}

print "Total words: $wc\n";
file_put_contents($wc_fn, $wc);

if($previous_wc && $previous_wc == $wc) {
	print "No change";
	exit(1);
}

print "Uploading new WC";

$event = $_SERVER['IFTTT_EVENT'];
$key = $_SERVER['IFTTT_KEY'];
$url = "https://maker.ifttt.com/trigger/$event/with/key/$key";
$data = ["value1" => $wc];

$options = array(
  'http' => array(
    'method'  => 'POST',
    'content' => json_encode($data),
    'header'=>  "Content-Type: application/json\r\n" .
                "Accept: application/json\r\n"
    )
);

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
$response = json_decode($result);
