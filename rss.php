<?php
declare(strict_types=1);

header('Content-Type: application/rss+xml; charset=UTF-8');

$baseUrl   = 'https://kytlicka.eu';
$listUrl   = $baseUrl . '/aktuality-a-oznameni/';
$maxItems  = 10; // kolik posledních článků posílat

/**
 * Bezpečné načtení URL.
 */
function fetchUrl(string $url): ?string
{
    $context = stream_context_create([
        'http' => [
            'timeout' => 6,
            'header'  => "User-Agent: SVJ-Kytlicka-RSS/1.0\r\n",
        ],
    ]);

    $html = @file_get_contents($url, false, $context);
    return $html === false ? null : $html;
}

/**
 * Z jedné stránky článku vytáhne datum publikace a převede na RFC2822 (DATE_RSS).
 * Očekává formát např. "05.11.2025".
 */
function extractPubDateFromArticle(string $html): ?string
{
    libxml_use_internal_errors(true);

    $dom = new DOMDocument();
    if (!@$dom->loadHTML($html)) {
        return null;
    }
    $xpath = new DOMXPath($dom);

    // 1) typické webnode/blog datum: <p class="blog-date">05.11.2025</p>
    $nodes = $xpath->query("//p[contains(@class,'blog-date') or contains(@class,'wn-blog-date')]");
    if ($nodes->length > 0) {
        $raw = trim($nodes->item(0)->textContent);
    } else {
        // 2) zkus <time datetime="...">
        $timeNodes = $xpath->query("//time[@datetime]");
        if ($timeNodes->length > 0) {
            $dt = $timeNodes->item(0)->getAttribute('datetime');
            $ts = strtotime($dt);
            return $ts ? date(DATE_RSS, $ts) : null;
        }
        return null;
    }

    // vytáhnout dd.mm.yyyy
    if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $raw, $m)) {
        $day   = (int)$m[1];
        $month = (int)$m[2];
        $year  = (int)$m[3];
        $ts = mktime(0, 0, 0, $month, $day, $year);
        return date(DATE_RSS, $ts);
    }

    return null;
}

/**
 * 1) Načteme seznam aktualit.
 */
$listHtml = fetchUrl($listUrl);

if ($listHtml === null) {
    // Nouzový minimální feed, aby se nevracela chyba.
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<rss version="2.0"><channel>';
    echo '<title>SVJ Kytlická Novinky</title>';
    echo '<link>' . htmlspecialchars($listUrl, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</link>';
    echo '<description>Nepodařilo se načíst obsah webu.</description>';
    echo '</channel></rss>';
    exit;
}

libxml_use_internal_errors(true);
$dom = new DOMDocument();
@$dom->loadHTML($listHtml);
$xpath = new DOMXPath($dom);

/**
 * 2) Najdeme odkazy na články.
 *   - bereme <h2><a href="/l/..."></a></h2>
 *   - deduplikujeme podle URL
 */
$articles = [];
$nodes = $xpath->query("//h2/a[contains(@href,'/l/')]");

foreach ($nodes as $a) {
    /** @var DOMElement $a */
    $href = trim($a->getAttribute('href'));
    $title = trim($a->textContent);

    if ($href === '' || $title === '') {
        continue;
    }

    if (strpos($href, 'http') !== 0) {
        $href = $baseUrl . $href;
    }

    // deduplikace podle URL (guid)
    if (!isset($articles[$href])) {
        $articles[$href] = $title;
    }

    if (count($articles) >= $maxItems) {
        break;
    }
}

/**
 * 3) Sestavíme RSS.
 */
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

$rss = $xml->createElement('rss');
$rss->setAttribute('version', '2.0');
$xml->appendChild($rss);

$channel = $xml->createElement('channel');
$rss->appendChild($channel);

$channel->appendChild($xml->createElement('title', 'SVJ Kytlická Novinky'));
$channel->appendChild($xml->createElement('link', $listUrl));
$channel->appendChild($xml->createElement('description', 'Novinky zveřejněné na webu SVJ Kytlická'));

$lastBuildTs = 0;

foreach ($articles as $url => $title) {
    $html = fetchUrl($url);
    $pubDate = $html ? extractPubDateFromArticle($html) : null;

    // když nenajdeme datum, vezmeme dnešek (ale to by mělo být spíš výjimečné)
    if ($pubDate === null) {
        $ts = time();
        $pubDate = date(DATE_RSS, $ts);
    } else {
        $ts = strtotime($pubDate) ?: time();
    }

    if ($ts > $lastBuildTs) {
        $lastBuildTs = $ts;
    }

    $item = $xml->createElement('item');
    $item->appendChild($xml->createElement('title', $title));
    $item->appendChild($xml->createElement('link', $url));
    $item->appendChild($xml->createElement('description', $title));
    $item->appendChild($xml->createElement('pubDate', $pubDate));

    $guid = $xml->createElement('guid', $url);
    $guid->setAttribute('isPermaLink', 'true');
    $item->appendChild($guid);

    $channel->appendChild($item);
}

if ($lastBuildTs > 0) {
    $channel->appendChild(
        $xml->createElement('lastBuildDate', date(DATE_RSS, $lastBuildTs))
    );
}

echo $xml->saveXML();
