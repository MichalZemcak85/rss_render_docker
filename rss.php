<?php
header('Content-Type: application/rss+xml; charset=UTF-8');

$url = 'https://kytlicka.eu/aktuality-a-oznameni/';
$html = @file_get_contents($url);

if ($html === false) {
  http_response_code(500);
  echo "Cannot load source page";
  exit;
}

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

/**
 * Webnode články typicky mají URL ve tvaru /l/slug/
 * Vytáhneme všechny odkazy, které obsahují "/l/" a jsou na doméně kytlicka.eu
 */
$nodes = $xpath->query("//a[contains(@href,'/l/')]");

$items = [];
foreach ($nodes as $a) {
  $href = trim($a->getAttribute('href'));
  if (!$href) continue;

  // Absolutní URL
  if (strpos($href, 'http') !== 0) {
    $href = 'https://kytlicka.eu' . $href;
  }

  // Titulek: text odkazu
  $title = trim(preg_replace('/\s+/', ' ', $a->textContent));
  if ($title === '') continue;

  // Dedup podle linku
  if (isset($items[$href])) continue;

  // Zkus najít datum poblíž odkazu (v praxi bývá v rodičích)
  $dateText = '';
  $dateNode = $xpath->query(".//ancestor::*[self::article or self::div][1]//p[contains(@class,'blog-date') or contains(@class,'date')]", $a);
  if ($dateNode && $dateNode->length > 0) {
    $dateText = trim($dateNode->item(0)->textContent);
  }

  $ts = $dateText ? strtotime($dateText) : time();

  $items[$href] = [
    'title' => $title,
    'link'  => $href,
    'desc'  => $title,
    'ts'    => $ts ?: time(),
  ];
}

// vezmeme posledních N unikátních položek (např. 10)
$items = array_values($items);
$items = array_slice($items, 0, 10);

// RSS XML
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

$rss = $xml->createElement('rss');
$rss->setAttribute('version', '2.0');
$channel = $xml->createElement('channel');

$channel->appendChild($xml->createElement('title', 'SVJ Kytlická Novinky'));
$channel->appendChild($xml->createElement('link', $url));
$channel->appendChild($xml->createElement('description', 'Novinky zveřejněné na webu SVJ Kytlická'));

if (!empty($items)) {
  $channel->appendChild($xml->createElement('lastBuildDate', gmdate(DATE_RSS, $items[0]['ts'])));
}

foreach ($items as $it) {
  $item = $xml->createElement('item');
  $item->appendChild($xml->createElement('title', $it['title']));
  $item->appendChild($xml->createElement('link', $it['link']));
  $item->appendChild($xml->createElement('description', $it['desc']));
  $item->appendChild($xml->createElement('pubDate', gmdate(DATE_RSS, $it['ts'])));

  $guid = $xml->createElement('guid', $it['link']);
  $guid->setAttribute('isPermaLink', 'true');
  $item->appendChild($guid);

  $channel->appendChild($item);
}

$rss->appendChild($channel);
$xml->appendChild($rss);

echo $xml->saveXML();
