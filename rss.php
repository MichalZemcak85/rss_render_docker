<?php
header("Content-Type: application/rss+xml; charset=UTF-8");
libxml_use_internal_errors(true);

$baseUrl = 'https://kytlicka.eu';
$homeUrl = $baseUrl . '/';

// Načtení domovské stránky
$html = @file_get_contents($homeUrl);
if ($html === false) {
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<rss version="2.0"><channel>';
    echo '<title>SVJ Kytlická Novinky</title>';
    echo '<link>' . htmlspecialchars($homeUrl) . '</link>';
    echo '<description>Nelze načíst domovskou stránku.</description>';
    echo '</channel></rss>';
    exit;
}

$dom = new DOMDocument();
$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

// Najdeme všechny odkazy na články (/l/)
$linkNodes = $xpath->query("//a[contains(@href, '/l/')]");
$articles = [];

foreach ($linkNodes as $node) {
    $href = trim($node->getAttribute('href'));
    if (!$href) continue;

    if (strpos($href, 'http') !== 0) {
        $href = rtrim($baseUrl, '/') . $href;
    }

    $title = trim($node->textContent);
    if ($title === '') continue;

    // Hledáme datum poblíž odkazu (např. 05.11.2025)
    $dateText = '';
    $context = $node->parentNode;
    if ($context && preg_match('/\d{1,2}\.\d{1,2}\.\d{4}/', $context->textContent, $m)) {
        $dateText = $m[0];
    }

    $pubTs = time();
    if ($dateText) {
        [$d, $m, $y] = explode('.', $dateText);
        $pubTs = mktime(0, 0, 0, (int)$m, (int)$d, (int)$y);
    }

    // Přidáme článek
    $articles[] = [
        'title' => $title,
        'link' => $href,
        'pubTs' => $pubTs,
    ];
}

// Odstraníme duplicitní odkazy
$articles = array_unique($articles, SORT_REGULAR);

// Seřadíme podle data
usort($articles, fn($a, $b) => $b['pubTs'] <=> $a['pubTs']);
$articles = array_slice($articles, 0, 5); // max 5 článků

// Vytvoření RSS
echo '<?xml version="1.0" encoding="UTF-8"?>';
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

$rss = $xml->createElement('rss');
$rss->setAttribute('version', '2.0');
$xml->appendChild($rss);

$channel = $xml->createElement('channel');
$rss->appendChild($channel);

$channel->appendChild($xml->createElement('title', 'SVJ Kytlická Novinky'));
$channel->appendChild($xml->createElement('link', $homeUrl));
$channel->appendChild($xml->createElement('description', 'Novinky zveřejněné na webu SVJ Kytlická'));

if (!empty($articles)) {
    $lastBuild = max(array_column($articles, 'pubTs'));
    $channel->appendChild($xml->createElement('lastBuildDate', date(DATE_RSS, $lastBuild)));

    foreach ($articles as $art) {
        $item = $xml->createElement('item');
        $item->appendChild($xml->createElement('title', htmlspecialchars($art['title'], ENT_XML1)));
        $item->appendChild($xml->createElement('link', htmlspecialchars($art['link'], ENT_XML1)));
        $item->appendChild($xml->createElement('description', htmlspecialchars($art['title'], ENT_XML1)));
        $item->appendChild($xml->createElement('pubDate', date(DATE_RSS, $art['pubTs'])));
        $item->appendChild($xml->createElement('guid', htmlspecialchars($art['link'], ENT_XML1)));
        $channel->appendChild($item);
    }
}

echo $xml->saveXML();
