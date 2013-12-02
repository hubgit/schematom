<?php

define('ATOM', 'http://www.w3.org/2005/Atom');

$url = isset($_GET['url']) ? $_GET['url'] : (isset($argv[1]) ? $argv[1] : null);

if (!$url) {
	require __DIR__ . '/form.html';
	exit();
}

$data = extract_feed($url);
//print json_encode($data, JSON_PRETTY_PRINT);
output_atom($data);

function extract_feed($url) {
	$doc = new DOMDocument;
	libxml_use_internal_errors(true);
	$doc->loadHTMLFile($url);
	libxml_use_internal_errors(false);
	$xpath = new DOMXPath($doc);

	$feed = array(
		'id' => $url,
		'title' => $xpath->evaluate('string(head/title)'),
		'updated' => new DateTime,
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
			'published' => strtotime($published),
			'updated' => $updated ? strtotime($updated) : strtotime($published),
			'author' => array(
				'name' => $xpath->evaluate('string(.//*[@itemprop="author"])', $entry),
				'url' => $xpath->evaluate('string(.//*[@itemprop="author"]/@href)', $entry),
			)
		);
	}

	return $feed;
}

function output_atom($data) {
	$doc = new DOMDocument();

	$feed = $doc->appendChild($doc->createElementNS(ATOM, 'feed'));
	$feed->appendChild($doc->createElementNS(ATOM, 'id', htmlspecialchars($data['id'])));
	$feed->appendChild($doc->createElementNS(ATOM, 'title', htmlspecialchars($data['title'])));
	$feed->appendChild($doc->createElementNS(ATOM, 'updated', date(DATE_ATOM, $data['updated'])));

	foreach ($data['entry'] as $item) {
		$entry = $feed->appendChild($doc->createElementNS(ATOM, 'entry'));
		$entry->appendChild($doc->createElementNS(ATOM, 'id', htmlspecialchars($item['id'])));
		$entry->appendChild($doc->createElementNS(ATOM, 'title', htmlspecialchars($item['title'])));
		$entry->appendChild($doc->createElementNS(ATOM, 'published', date(DATE_ATOM, $item['published'])));
		$entry->appendChild($doc->createElementNS(ATOM, 'updated', date(DATE_ATOM, $item['updated'])));

		// if the author is the same for all entries, could add the author to the feed element instead
		$author = $entry->appendChild($doc->createElementNS(ATOM, 'author'));
		$author->appendChild($doc->createElementNS(ATOM, 'name', htmlspecialchars($item['author']['name'])));
		$author->appendChild($doc->createElementNS(ATOM, 'url', htmlspecialchars($item['author']['url'])));
	}

	$doc->encoding = 'UTF-8';
	$doc->formatOutput = true;

	print $doc->saveXML();
}

