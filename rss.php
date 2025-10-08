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
$titleNode = $xpath->query("//h2")[0];       // Nadpis článku
$linkNode  = $xpath->query("//h2/a")[0];     // Odkaz článku
$dateNode  = $xpath->query("//em | //i | //p")[0]; // Najdi datum i bez class

$title = $titleNode ? trim($titleNode->nodeValue) : "Neznámý titulek";
$link  = $linkNode ? $linkNode->getAttribute("href") : "https://kytlicka.eu";
if (strpos($link, "http") !== 0) {
    $link = "https://kytlicka.eu" . $link;
}
$description = $title;

// 🧭 Zpracuj datum (formát 23.09.2025)
$dateText = $dateNode ? trim($dateNode->nodeValue) : "";

if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $dateText, $m)) {
    $day = (int)$m[1];
    $month = (int)$m[2];
    $year = (int)$m[3];
    $timestamp = mktime(0, 0, 0, $month, $day, $year);
} else {
    $timestamp = strtotime($dateText);
}

if (!$timestamp) {
    $timestamp = time(); // fallback
}

$pubDate = date(DATE_RSS, $timestamp);

// 🧱 Vytvoření XML
$xml = new DOMDocument("1.0", "UTF-8");
$xml->formatOutput = true;

$rss = $xml->createElement("rss");
$rss->setAttribute("version", "2.0");

$channel = $xml->createElement("channel");
$channel->appendChild($xml->createElement("title", "SVJ Kytlická Novinky"));
$channel->appendChild($xml->createElement("link", $url));
$channel->appendChild($xml->createElement("description", "Novinky zveřejněné na webu SVJ Kytlická"));
$channel->appendChild($xml->createElement("lastBuildDate", $pubDate));

// 📰 Jedna položka
$item = $xml->createElement("item");
$item->appendChild($xml->createElement("title", $title));
$item->appendChild($xml->createElement("link", $link));
$item->appendChild($xml->createElement("description", $description));
$item->appendChild($xml->createElement("pubDate", $pubDate));
$item->appendChild($xml->createElement("guid", $link));
$channel->appendChild($item);

$rss->appendChild($channel);
$xml->appendChild($rss);

// Výstup XML
echo $xml->saveXML();
?>
