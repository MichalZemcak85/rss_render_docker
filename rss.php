<?php
declare(strict_types=1);

// 1) ŽÁDNÉ echo/print před hlavičkami!
header('Content-Type: application/rss+xml; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

libxml_use_internal_errors(true);

const HOME_URL = 'https://kytlicka.eu/';

/**
 * Bezpečné stažení HTML (User-Agent + timeout)
 */
function fetchHtml(string $url): string
{
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'timeout' => 12,
            'header'  => "User-Agent: SVJ-Kytlicka-RSS/1.0 (+https://kytlicka.eu)\r\n",
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);

    $html = @file_get_contents($url, false, $ctx);
    if ($html === false || trim($html) === '') {
        // V RSS raději vrátíme validní feed bez položek, než fatální chybu.
        return '';
    }
    return $html;
}

/**
 * Najde první datum ve formátu dd.mm.yyyy v textu.
 */
function extractDate(string $text): ?string
{
    if (preg_match('/\b(\d{2}\.\d{2}\.\d{4})\b/u', $text, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Převede dd.mm.yyyy na RFC2822 (DATE_RSS) v Europe/Prague, čas 00:00:00
 */
function dateToRss(string $dmy): string
{
    $tz = new DateTimeZone('Europe/Prague');
    $dt = DateTime::createFromFormat('d.m.Y H:i:s', $dmy . ' 00:00:00', $tz);
    if (!$dt) {
        $dt = new DateTime('now', $tz);
    }
    return $dt->format(DATE_RSS);
}

/**
 * Z homepage vytáhne položky ze sekce "Nejnovější aktuality".
 */
function parseHomepageItems(string $html): array
{
    if ($html === '') return [];

    $dom = new DOMDocument();
    // Potlačí warningy u “nečistého” HTML
    @$dom->loadHTML($html);
    $xp = new DOMXPath($dom);

    // 1) Najdi nadpis sekce “Nejnovější aktuality”
    // Webnode to může mít v h1/h2/h3 apod., proto hledáme obecně.
    $heading = $xp->query("//*[self::h1 or self::h2 or self::h3 or self::h4][contains(normalize-space(.), 'Nejnovější aktuality')]");
    if (!$heading || $heading->length === 0) {
        return [];
    }

    // 2) Vezmi rozumný kontejner sekce – často parent/ancestor "section/div"
    $h = $heading->item(0);
    $container = null;

    // Zkus nejbližší ancestor, který v sobě má víc odkazů na /l/
    $ancestors = $xp->query("ancestor::*[self::section or self::div][.//a[contains(@href,'/l/')]]", $h);
    if ($ancestors && $ancestors->length > 0) {
        $container = $ancestors->item($ancestors->length - 1); // vezmeme nejvyšší vhodný v rámci té sekce
    } else {
        $container = $h->parentNode;
    }

    if (!$container) return [];

    // 3) V sekci najdi všechny odkazy na články (/l/)
    $links = $xp->query(".//a[contains(@href,'/l/')]", $container);

    $items = [];
    $seen = [];

    foreach ($links as $a) {
        /** @var DOMElement $a */
        $href = trim($a->getAttribute('href'));
        if ($href === '') continue;

        // Normalizace na absolutní URL
        if (strpos($href, 'http') !== 0) {
            $href = rtrim(HOME_URL, '/') . '/' . ltrim($href, '/');
        }

        // Dedup (na homepage může být více odkazů na stejnou stránku)
        if (isset($seen[$href])) continue;
        $seen[$href] = true;

        // Titulek: buď text v <a>, nebo nejbližší nadpis v “kartě”
        $title = trim(preg_replace('/\s+/u', ' ', $a->textContent ?? ''));
        if ($title === '' || mb_strlen($title) < 3) {
            // zkus najít titulek v okolí (např. h2/h3 v rámci stejného bloku)
            $card = $xp->query("ancestor::*[self::div or self::article][.//a[@href='" . htmlspecialchars($a->getAttribute('href'), ENT_QUOTES) . "']]", $a);
            if ($card && $card->length > 0) {
                $tNode = $xp->query(".//*[self::h2 or self::h3 or self::h4][1]", $card->item(0));
                if ($tNode && $tNode->length > 0) {
                    $title = trim(preg_replace('/\s+/u', ' ', $tNode->item(0)->textContent ?? ''));
                }
            }
        }
        if ($title === '') $title = 'Aktualita SVJ Kytlická';

        // Datum: hledej dd.mm.yyyy v rámci stejné “karty” / blízkého okolí
        $date = null;

        // a) nejbližší parent blok s textem obsahujícím datum
        $cardCandidates = $xp->query("ancestor::*[self::div or self::article][1]", $a);
        if ($cardCandidates && $cardCandidates->length > 0) {
            $date = extractDate($cardCandidates->item(0)->textContent ?? '');
        }

        // b) fallback: zkus vzít datum z nejbližších sourozenců/rodiče
        if ($date === null && $a->parentNode) {
            $date = extractDate($a->parentNode->textContent ?? '');
        }

        // Když datum nenajdeme, položku radši přeskočíme (MailerLite pak neblbne)
        if ($date === null) continue;

        $items[] = [
            'title'       => $title,
            'link'        => $href,
            'description' => $title,
            'pubDateDmy'  => $date,
        ];
    }

    // 4) Seřadit podle data (nejnovější první)
    usort($items, function ($a, $b) {
        $ta = strtotime(str_replace('.', '-', $a['pubDateDmy']));
        $tb = strtotime(str_replace('.', '-', $b['pubDateDmy']));
        return ($tb <=> $ta);
    });

    // 5) Necháme max 10 (stačí)
    return array_slice($items, 0, 10);
}

/**
 * Vygeneruje RSS 2.0 XML.
 */
function buildRss(array $items): string
{
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;

    $rss = $xml->createElement('rss');
    $rss->setAttribute('version', '2.0');

    $channel = $xml->createElement('channel');
    $channel->appendChild($xml->createElement('title', 'SVJ Kytlická Novinky'));
    $channel->appendChild($xml->createElement('link', HOME_URL));
    $channel->appendChild($xml->createElement('description', 'Novinky zveřejněné na webu SVJ Kytlická'));

    // lastBuildDate nastavíme jako nejnovější publikované datum (ne teď)
    if (!empty($items)) {
        $channel->appendChild($xml->createElement('lastBuildDate', dateToRss($items[0]['pubDateDmy'])));
    }

    foreach ($items as $it) {
        $item = $xml->createElement('item');

        $item->appendChild($xml->createElement('title', $it['title']));
        $item->appendChild($xml->createElement('link', $it['link']));
        $item->appendChild($xml->createElement('description', $it['description']));
        $item->appendChild($xml->createElement('pubDate', dateToRss($it['pubDateDmy'])));

        $guid = $xml->createElement('guid', $it['link']);
        $guid->setAttribute('isPermaLink', 'true');
        $item->appendChild($guid);

        $channel->appendChild($item);
    }

    $rss->appendChild($channel);
    $xml->appendChild($rss);

    return $xml->saveXML();
}

// === MAIN ===
$html  = fetchHtml(HOME_URL);
$items = parseHomepageItems($html);
echo buildRss($items);
