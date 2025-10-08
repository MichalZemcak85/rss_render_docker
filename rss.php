<?php
header("Content-Type: application/rss+xml; charset=UTF-8");

// URL webu SVJ
$url = "https://kytlicka.eu/aktuality-a-oznameni/";

// Načti HTML
$html = @file_get_contents($url);
if ($html === false) {
    die("Nelze načíst obsah stránky.");
}

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

// Najdi první článek (nejnovější)
$titleNode = $xpath->query("//h2")[0];
$dateNode = $xpath->query("//p[contains(@class,'blog-date')]")[0];
$linkNode = $xpath->query("//h2/a")[0];

// Zpracuj data
$title = $titleNode ? trim($titleNode->nodeValue) : "Neznámý titulek";
$link = $linkNode ? $linkNode->getAttribute("href") : "https://kytlicka.eu";
if (strpos($link, "http") !== 0) {
    $link = "https://kytlicka.eu" . $link;
}
$description = $title;

// Zpracuj datum
$dateText = $dateNode ? trim($dateNode->nodeValue) : "";
$timestamp = strtotime($dateText);

// Připrav cache soubor
$cacheFile = __DIR__ . '/last_item.json';
$lastItem = file_exists($cacheFile) ? json_decode(file_get_contents($cacheFile), true) : [];

// Pokud je nový článek, uloží se nový timestamp, jinak zůstane starý
if (!isset($lastItem['title']) || $lastItem['title'] !== $title) {
    $pubDate = date(DATE_RSS, $timestamp ?: time());
    $lastBuildDate = $pubDate;
    file_put_contents($cacheFile, json_encode(['title' => $title, 'link' => $link, 'pubDate' => $pubDate]));
} else {
    $pubDate = $lastItem['pubDate'];
    $lastBuildDate = $lastItem['pubDate'];
}

// 🧱 Vytvoření XML
$xml = new DOMDocument("1.0", "UTF-8");
$xml->formatOutput = true;

$rss = $xml->createElement("rss");
$rss->setAttribute("version", "2.0");
$channel = $xml->createElement("channel");

// Metadata kanálu
$channel->appendChild($xml->createElement("title", "SVJ Kytlická Novinky"));
$channel->appendChild($xml->createElement("link", $url));
$channel->appendChild($xml->createElement("description", "Novinky zveřejněné na webu SVJ Kytlická"));
$channel->appendChild($xml->createElement("lastBuildDate", $lastBuildDate));

// Položka
$item = $xml->createElement("item");
$item->appendChild($xml->createElement("title", htmlspecialchars($title)));
$item->appendChild($xml->createElement("link", htmlspecialchars($link)));
$item->appendChild($xml->createElement("description", htmlspecialchars($description)));
$item->appendChild($xml->createElement("pubDate", $pubDate));
$item->appendChild($xml->createElement("guid", htmlspecialchars($link)));

$channel->appendChild($item);
$rss->appendChild($channel);
$xml->appendChild($rss);

// Výstup
echo $xml->saveXML();
?>
