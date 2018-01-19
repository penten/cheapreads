<?php

require 'evernote.php';

$wc = 0;
$fulltext = '';
$tag = $argv[1];
$wc_fn = "/tmp/${tag}_evernote_wc";
$previous_wc = file_get_contents($wc_fn);

$draft_curf = '/usr/share/nginx/html/draft_e39543hd.txt';
$draft_prevf = '/usr/share/nginx/html/draft_edfsf43hd.txt';

function recordIFTTT($event, $value) {
	$key = $_SERVER['IFTTT_KEY'];
	$url = "https://maker.ifttt.com/trigger/$event/with/key/$key";
	$data = ["value1" => $value];

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
	return json_decode($result);
}


foreach(getTag($tag) as $scene) {
	// Count only the scenes in the book, exclude notes
	if(strpos($scene['title'], 'Scene') !== false) {
		$raw_text = noteToText($scene['body']);
		$wc += str_word_count($raw_text);

		$fulltext .= "### ${scene['title']} \n$raw_text\n\n";
	}
}

// save the previous draft
rename($draft_curf, $draft_prevf);

// post the full text of the draft somewhere so I can look at it
file_put_contents($draft_curf, "\xEF\xBB\xBF" . $fulltext);

// get the number of changes since the last time
$cmd = "git diff --no-index --stat $draft_prevf $draft_curf | head -n1 | awk '{ print \$5}'";
$changes = trim(shell_exec($cmd));

print "Changes: $changes\n";
print "Total words: $wc\n";

// record current word count
file_put_contents($wc_fn, $wc);

if(!$previous_wc || $previous_wc != $wc) {
	print "Uploading new WC\n";
	recordIFTTT('word_count', $wc);
}

if(is_numeric($changes)) {
	print "Uploading change count\n";
	recordIFTTT('changes', $changes);
}
