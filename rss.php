<?php
// ŽÁDNÉ MEZERY ANI ŘÁDKY PŘED TÍMTO ŘÁDKEM

header('Content-Type: application/rss+xml; charset=UTF-8');
libxml_use_internal_errors(true);

/**
 * Načte HTML stránku a vrátí DOMXPath nebo null.
 */
function get_xpath($url) {
    $html = @file_get_contents($url);
    if ($html === false) {
        return null;
    }

    $dom = new DOMDocument();
    $dom->loadHTML($html);
    return new DOMXPath($dom);
}

/**
 * Převede datum ve formátu "dd.mm.yyyy" na DATE_RSS.
 * Když se nepodaří, vrátí aktuální čas.
 */
function parse_date_to_rss($dateStr) {
    $dateStr = trim($dateStr);
    $ts = strtotime($dateStr);
    if ($ts === false) {
        $ts = time();
    }
    return gmdate(DATE_RSS, $ts);
}

$items = [];

/*
 * 1) Poslední články z "Aktuality a oznámení"
 * Předpoklad: články jsou odkazy <a> obsahující <h2> a někde poblíž datum.
 * Když selektor nesedí na strukturu Webnode, jen ho bude potřeba doladit,
 * ale skript jako takový poběží a nebude házet hlavičkové chyby.
 */
$xpAkt = get_xpath('https://kytlicka.eu/aktuality-a-oznameni/');
if ($xpAkt) {
    // Najdeme všechny odkazy, které vypadají jako detail článku (/l/...)
    $links = $xpAkt->query("//a[contains(@href, '/l/')]");
    foreach ($links as $a) {
        // Titulek = nejbližší <h2> uvnitř odkazu
        $titleNode = null;
        foreach ($a->childNodes as $child) {
            if (strtolower($child->nodeName) === 'h2') {
                $titleNode = $child;
                break;
            }
        }
        if (!$titleNode) {
            continue;
        }

        $title = trim($titleNode->nodeValue);
        if ($title === '') {
            continue;
        }

        $href = $a->getAttribute('href');
        if (strpos($href, 'http') !== 0) {
            $href = 'https://kytlicka.eu' . $href;
        }

        // Datum: první odstavec s třídou obsahující "blog-date" v rámci stejného bloku
        $dateNode = $xpAkt->query(".//p[contains(@class,'blog-date')][1]", $a)->item(0);
        $dateStr = $dateNode ? $dateNode->nodeValue : '';
        $pubDate = $dateStr ? parse_date_to_rss($dateStr) : gmdate(DATE_RSS);

        $items[] = [
            'title'       => $title,
            'link'        => $href,
            'description' => $title,
            'pubDate'     => $pubDate,
            'guid'        => $href,
        ];

        // Vezmeme jen první (nejnovější) z /aktualit
        if (count($items) >= 1) {
            break;
        }
    }
}

/*
 * 2) Pozvánka na shromáždění z hlavní stránky (sekce "Nejnovější aktuality")
 * Je to trochu heuristika – bere první odkaz v boxu, kde je text "Pozvánka na Shromáždění".
 */
$xpHome = get_xpath('https://kytlicka.eu/');
if ($xpHome) {
    $pozvanky = $xpHome->query(
        "//section[contains(., 'Nejnovější aktuality')]//a[contains(translate(., 'PZANKSHROMÁDĚÍ','pzankshromáděí'), 'pozvánka na shromáždění')]"
    );

    if ($pozvanky && $pozvanky->length > 0) {
        $a = $pozvanky->item(0);

        $title = trim($a->textContent);
        $href = $a->getAttribute('href');
        if (strpos($href, 'http') !== 0) {
            $href = 'https://kytlicka.eu' . $href;
        }

        // Zkusíme z cílové stránky vytáhnout datum (první <time> nebo čísla "dd.mm.yyyy")
        $pubDate = gmdate(DATE_RSS);
        $xpPoz = get_xpath($href);
        if ($xpPoz) {
            $timeNode = $xpPoz->query("//time")->item(0);
            if ($timeNode && trim($timeNode->nodeValue) !== '') {
                $pubDate = parse_date_to_rss($timeNode->nodeValue);
            } else {
                $txt = $xpPoz->document->textContent ?? '';
                if (preg_match('/\b(\d{1,2}\.\d{1,2}\.\d{4})\b/u', $txt, $m)) {
                    $pubDate = parse_date_to_rss($m[1]);
                }
            }
        }

        $items[] = [
            'title'       => $title,
            'link'        => $href,
            'description' => $title,
            'pubDate'     => $pubDate,
            'guid'        => $href,
        ];
    }
}

/*
 * 3) Záloha: pokud nic nezískáme (struktura webu se změní), dáme aspoň prázdný kanál
 * bez itemů, ale validní RSS.
 */

if (!empty($items)) {
    // Seřadíme podle pubDate (nejnovější první)
    usort($items, function ($a, $b) {
        return strcmp($b['pubDate'], $a['pubDate']);
    });
}

$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

$rss = $xml->createElement('rss');
$rss->setAttribute('version', '2.0');

$channel = $xml->createElement('channel');
$channel->appendChild($xml->createElement('title', 'SVJ Kytlická Novinky'));
$channel->appendChild($xml->createElement('link', 'https://kytlicka.eu/aktuality-a-oznameni/'));
$channel->appendChild($xml->createElement('description', 'Novinky zveřejněné na webu SVJ Kytlická'));

if (!empty($items)) {
    $channel->appendChild($xml->createElement('lastBuildDate', $items[0]['pubDate']));
}

foreach ($items as $data) {
    $item = $xml->createElement('item');
    $item->appendChild($xml->createElement('title', $data['title']));
    $item->appendChild($xml->createElement('link', $data['link']));
    $item->appendChild($xml->createElement('description', $data['description']));
    $item->appendChild($xml->createElement('pubDate', $data['pubDate']));

    $guid = $xml->createElement('guid', $data['guid']);
    $guid->setAttribute('isPermaLink', 'true');
    $item->appendChild($guid);

    $channel->appendChild($item);
}

$rss->appendChild($channel);
$xml->appendChild($rss);

// Jediný výstup – platné RSS XML
echo $xml->saveXML();
