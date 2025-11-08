<?php
header("Content-Type: application/rss+xml; charset=UTF-8");
libxml_use_internal_errors(true);

// URL homepage, kde je blok "Nejnovější aktuality"
$baseUrl = 'https://kytlicka.eu';
$homeUrl = $baseUrl . '/';

// Načtení HTML
$html = @file_get_contents($homeUrl);
if ($html === false) {
    // Když nejde načíst web, vrať prázdný, ale validní RSS
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<rss version="2.0"><channel>';
    echo '<title>SVJ Kytlická Novinky</title>';
    echo '<link>' . htmlspecialchars($homeUrl) . '</link>';
    echo '<description>RSS dočasně nedostupné</description>';
    echo '</channel></rss>';
    exit;
}

$dom = new DOMDocument();
$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

// Najdeme nadpis "Nejnovější aktuality"
$headingNodes = $xpath->query(
    "//h2[contains(normalize-space(.), 'Nejnovější aktuality')]"
);

$items = [];

if ($headingNodes->length > 0) {
    // Sekce s kartami je blízko tohoto nadpisu – vezmeme rodiče
    $section = $headingNodes->item(0)->parentNode;

    // V rámci sekce najdeme všechny odkazy vedoucí na /l/...
    // (což jsou stránkované články/oznámení z Webnode)
    $linkNodes = $xpath->query(".//a[contains(@href, '/l/')]", $section);

    foreach ($linkNodes as $a) {
        $href = trim($a->getAttribute('href'));
        if (!$href) continue;

        // Opravíme relativní URL
        if (strpos($href, 'http') !== 0) {
            $href = rtrim($baseUrl, '/') . $href;
        }

        $title = trim($a->textContent);
        if ($title === '') continue;

        // Zkusíme najít datum poblíž – typicky je to element pod nadpisem.
        // Vezmeme nejbližší text s patternem dd.mm.yyyy
        $dateText = '';
        $dateNode = null;

        // Podíváme se na pár následujících sourozenců / rodičů
        $candidates = [
            $a->parentNode,
            $a->parentNode ? $a->parentNode->nextSibling : null,
            $a->parentNode ? $a->parentNode->parentNode : null
        ];

        foreach ($candidates as $cand) {
            if (!$cand instanceof DOMNode) continue;
            if (preg_match('/\d{2}\.\d{2}\.\d{4}/', $cand->textContent, $m)) {
                $dateText = $m[0];
                break;
            }
        }

        // Pokud jsme nic nenašli, použijeme dnešní den
        if ($dateText === '') {
            $pubTs = time();
        } else {
            // dd.mm.yyyy -> timestamp
            [$d, $m, $y] = explode('.', $dateText);
            $pubTs = mktime(0, 0, 0, (int)$m, (int)$d, (int)$y);
        }

        $items[] = [
            'title' => $title,
            'link' => $href,
            'pubTs' => $pubTs,
        ];
    }
}

// Seřadíme podle data (nejnovější první) a vezmeme max 3 položky
usort($items, function ($a, $b) {
    return $b['pubTs'] <=> $a['pubTs'];
});
$items = array_slice($items, 0, 3);

// Když se náhodou nic nenašlo, uděláme aspoň prázdný kanál
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

if (!empty($items)) {
    $lastBuild = max(array_column($items, 'pubTs'));
    $channel->appendChild($xml->createElement('lastBuildDate', date(DATE_RSS, $lastBuild)));

    foreach ($items as $it) {
        $item = $xml->createElement('item');
        $item->appendChild($xml->createElement('title', $it['title']));
        $item->appendChild($xml->createElement('link', $it['link']));
        $item->appendChild($xml->createElement('description', $it['title']));
        $item->appendChild($xml->createElement('pubDate', date(DATE_RSS, $it['pubTs'])));
        $item->appendChild($xml->createElement('guid', $it['link']));
        $channel->appendChild($item);
    }
}

echo $xml->saveXML();
