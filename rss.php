<?php
// DŮLEŽITÉ: žádné mezery, BOM ani prázdné řádky před tímto řádkem!

header('Content-Type: application/rss+xml; charset=UTF-8');

// 1) ZDE UDRŽUJ SEZNAM ČLÁNKŮ
//    Nejnovější VŽDY nahoře.
//    date = datum článku na webu (YYYY-MM-DD).

$items = [
    [
        'title'       => 'Pozvánka na Shromáždění – 25. 11. 2025',
        'link'        => 'https://kytlicka.eu/l/pozvanka-na-shromazdeni-25-11-2025/',
        'description' => 'Pozvánka na Shromáždění – 25. 11. 2025',
        'date'        => '2025-11-05',
    ],
    [
        'title'       => 'Fotovoltaika a jednotné odběrné místo – aktualizace projektu',
        'link'        => 'https://kytlicka.eu/l/fotovoltaika-a-jednotne-odberne-misto-aktualizace-projektu/',
        'description' => 'Fotovoltaika a jednotné odběrné místo – aktualizace projektu',
        'date'        => '2025-10-31',
    ],
    [
        'title'       => 'Zahajujeme topnou sezónu 2025/2026',
        'link'        => 'https://kytlicka.eu/l/zahajujeme-topnou-sezonu-2025-2026/',
        'description' => 'Zahajujeme topnou sezónu 2025/2026',
        'date'        => '2025-09-23',
    ],
];

// 2) Vytvoření XML

$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

$rss = $xml->createElement('rss');
$rss->setAttribute('version', '2.0');

$channel = $xml->createElement('channel');
$channel->appendChild($xml->createElement('title', 'SVJ Kytlická Novinky'));
$channel->appendChild($xml->createElement('link', 'https://kytlicka.eu/aktuality-a-oznameni/'));
$channel->appendChild($xml->createElement('description', 'Novinky zveřejněné na webu SVJ Kytlická'));

// lastBuildDate = datum nejnovější položky
if (!empty($items)) {
    $latestTs = strtotime($items[0]['date']);
    if ($latestTs) {
        $channel->appendChild(
            $xml->createElement('lastBuildDate', gmdate(DATE_RSS, $latestTs))
        );
    }
}

// jednotlivé položky
foreach ($items as $row) {
    $ts = strtotime($row['date']);
    if (!$ts) {
        continue;
    }

    $item = $xml->createElement('item');
    $item->appendChild($xml->createElement('title', $row['title']));
    $item->appendChild($xml->createElement('link', $row['link']));
    $item->appendChild($xml->createElement('description', $row['description']));
    $item->appendChild($xml->createElement('pubDate', gmdate(DATE_RSS, $ts)));

    // GUID = URL článku, trvalý identifikátor
    $guid = $xml->createElement('guid', $row['link']);
    $guid->setAttribute('isPermaLink', 'true');
    $item->appendChild($guid);

    $channel->appendChild($item);
}

$rss->appendChild($channel);
$xml->appendChild($rss);

echo $xml->saveXML();
