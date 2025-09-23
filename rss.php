<?php
header("Content-Type: application/rss+xml; charset=UTF-8");

// Vytvoření základního XML
$xml = new DOMDocument("1.0", "UTF-8");
$rss = $xml->createElement("rss");
$rss->setAttribute("version", "2.0");
$channel = $xml->createElement("channel");

// Hlavní metadata
$channel->appendChild($xml->createElement("title", "SVJ Kytlická Novinky"));
$channel->appendChild($xml->createElement("link", "https://kytlicka.eu/aktuality-a-oznameni/"));
$channel->appendChild($xml->createElement("description", "Novinky zveřejněné na webu SVJ Kytlická"));

// Ukázková položka – sem můžeš doplnit dynamická data
$item = $xml->createElement("item");
$item->appendChild($xml->createElement("title", "Aktualizace k opravě střechy – blok A"));
$item->appendChild($xml->createElement("link", "https://www.kytlicka.eu/l/aktualizace-k-oprave-strechy-blok-a/"));
$item->appendChild($xml->createElement("description", "Aktualizace k opravě střechy – blok A"));
$item->appendChild($xml->createElement("pubDate", date(DATE_RSS)));
$item->appendChild($xml->createElement("guid", "https://www.kytlicka.eu/l/aktualizace-k-oprave-strechy-blok-a/"));
$channel->appendChild($item);

// Uzavření XML
$rss->appendChild($channel);
$xml->appendChild($rss);

// Výstup
echo $xml->saveXML();
?>
