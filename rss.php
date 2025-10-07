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
if ($timestamp) {
    $pubDate = date(DATE_RSS, $timestamp);
} else {
    // fallback – pevné datum, aby se feed neměnil každý den
    $pubDate = "Tue, 23 Sep 2025 00:00:00 +0000";
}

// 🧩 Ulož si poslední článek pro kontrolu duplicity
$cacheFile = __DIR__ . '/last_title.txt';
$lastTitle = file_exists($cacheFile) ? trim(file_get_contents($cacheFile)) : '';

if ($lastTitle !== $title) {
    // nový článek – aktualizuj uložený název
    file_put_contents($cacheFile, $title);
} else {
    // žádná změna článku → použij starší datum (aby se neposílalo znovu)
    $pubDate = "Tue, 23 Sep 2025 00:00:00 +0000";
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
