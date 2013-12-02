<?php

$url = isset($_GET['url']) ? $_GET['url'] : (isset($argv[1]) ? $argv[1] : null);

if (!$url) {
	require __DIR__ . '/form.html';
	exit();
}

$doc = new DOMDocument;
libxml_use_internal_errors(true);
$doc->loadHTMLFile($url);
libxml_use_internal_errors(false);
$xpath = new DOMXPath($doc);

$feed = array(
	'id' => $url,
	'title' => $xpath->evaluate('string(head/title)'),
	'updated' => date(DATE_ATOM),
	'entry' => array(),
);

$entries = $xpath->query('//*[@itemscope][@itemtype="http://schema.org/BlogPosting"]');

foreach ($entries as $entry) {
	$itemrefs = preg_split('/\s+/', $entry->getAttribute('itemref'));

	foreach ($itemrefs as $itemref) {
		$clone = $doc->getElementById($itemref)->cloneNode(true);
		$clone->removeAttribute('id');
		$entry->appendChild($clone);
	}

	$url = $xpath->evaluate('string(.//a[@itemprop="url"]/@href)', $entry);

	$published = $xpath->evaluate('string(.//time[@itemprop="datePublished"]/@datetime)', $entry);
	$updated = $xpath->evaluate('string(.//time[@itemprop="dateModified"]/@datetime)', $entry);

	$feed['entry'][] = array(
		'id' => $url,
		'title' => $xpath->evaluate('string(.//*[@itemprop="name"])', $entry),
		'published' => $published,
		'updated' => $updated ?: $published,
		'author' => array(
			'name' => $xpath->evaluate('string(.//*[@itemprop="author"])', $entry),
			'url' => $xpath->evaluate('string(.//*[@itemprop="author"]/@href)', $entry),
		)
	);
}

print json_encode($feed, JSON_PRETTY_PRINT);
