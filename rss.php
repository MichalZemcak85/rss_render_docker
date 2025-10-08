<?php
header("Content-Type: application/rss+xml; charset=UTF-8");

// Načti HTML ze stránky SVJ
$url = "https://kytlicka.eu/aktuality-a-oznameni/";
$html = @file_get_contents($url);

if ($html === false) {
    die("Nelze načíst obsah stránky.");
}

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

// Najdi první článek
$titleNode = $xpath->query("//h2")[0];  // Titulek článku
$dateNode = $xpath->query("//p[contains(@class,'blog-date')]")[0]; // Datum
$linkNode = $xpath->query("//h2/a")[0]; // Link

$title = $titleNode ? trim($titleNode->nodeValue) : "Neznámý titulek";
$link = $linkNode ? $linkNode->getAttribute("href") : "https://kytlicka.eu";
if (strpos($link, "http") !== 0) {
    $link = "https://kytlicka.eu" . $link; // Oprava relativního odkazu
}
$description = $title; // můžeš doplnit úvod článku

// 🧭 Zpracuj datum (formát 23.09.2025)
$dateText = $dateNode ? trim($dateNode->nodeValue) : "";
if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $dateText, $matches)) {
    $day = (int)$matches[1];
    $month = (int)$matches[2];
    $year = (int)$matches[3];
    $timestamp = mktime(0, 0, 0, $month, $day, $year);
} else {
    // fallback pro jiné formáty
    $timestamp = strtotime($dateText);
}

// Pokud se datum nepodaří načíst, použij aktuální
if (!$timestamp) {
    $timestamp = time();
}

$pubDate = date(DATE_RSS, $timestamp);

// Vytvoření XML
$xml = new DOMDocument("1.0", "UTF-8");
$xml->formatOutput = true;

$rss = $xml->createElement("rss");
$rss->setAttribute("version", "2.0");
$channel = $xml->createElement("channel");

// Metadata kanálu
$channel->appendChild($xml->createElement("title", "SVJ Kytlická Novinky"));
$channel->appendChild($xml->createElement("link", $url));
$channel->appendChild($xml->createElement("description", "Novinky zveřejněné na webu SVJ Kytlická"));
$channel->appendChild($xml->createElement("lastBuildDate", $pubDate));

// Položka článku
$item = $xml->createElement("item");
$item->appendChild($xml->createElement("title", $title));
$item->appendChild($xml->createElement("link", $link));
$item->appendChild($xml->createElement("description", $description));
$item->appendChild($xml->createElement("pubDate", $pubDate));
$item->appendChild($xml->createElement("guid", $link));
$channel->appendChild($item);

// Dokončení XML
$rss->appendChild($channel);
$xml->appendChild($rss);
echo $xml->saveXML();
?>
