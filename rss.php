<?php
declare(strict_types=1);

/**
 * RSS generator for Webnode page:
 * https://kytlicka.eu/aktuality-a-oznameni/
 *
 * - Outputs stable pubDate from Webnode (dd.mm.yyyy), not "now"
 * - Outputs lastBuildDate = newest item pubDate
 * - Includes multiple latest items (default 10)
 * - Adds ETag / 304 to speed up and reduce repeated downloads
 */

header('Content-Type: application/rss+xml; charset=UTF-8');

$SOURCE_URL = 'https://kytlicka.eu/aktuality-a-oznameni/';
$BASE_URL   = 'https://kytlicka.eu';
$TZ         = new DateTimeZone('Europe/Prague');
$MAX_ITEMS  = 10;

// Fetch HTML with timeout + user-agent
$context = stream_context_create([
  'http' => [
    'method'  => 'GET',
    'timeout' => 12,
    'header'  => "User-Agent: SVJ-Kytlicka-RSS/1.0 (+$BASE_URL)\r\n",
  ],
  'ssl' => [
    'verify_peer'      => true,
    'verify_peer_name' => true,
  ],
]);

$html = @file_get_contents($SOURCE_URL, false, $context);
if ($html === false || trim($html) === '') {
  http_response_code(502);
  echo "<!-- ERROR: Nelze načíst obsah stránky $SOURCE_URL -->";
  exit;
}

libxml_use_internal_errors(true);

$dom = new DOMDocument();
$dom->loadHTML($html);

$xpath = new DOMXPath($dom);

// Webnode stránka má články jako: H2 -> datum jako textový uzel -> perex (často <p>)
$h2Links = $xpath->query('//h2/a');

$items = [];
if ($h2Links) {
  foreach ($h2Links as $a) {
    if (count($items) >= $MAX_ITEMS) break;

    $title = trim($a->textContent ?? '');
    $href  = trim($a->getAttribute('href') ?? '');

    if ($title === '' || $href === '') continue;

    // Absolutní URL
    if (stripos($href, 'http') !== 0) {
      $href = rtrim($BASE_URL, '/') . '/' . ltrim($href, '/');
    }

    // Najdi datum (typicky je hned za H2 jako text: "19.12.2025")
    $dateStr = '';
    $h2 = $a->parentNode; // <h2>
    $node = $h2 ? $h2->nextSibling : null;

    // Projdi pár sourozenců a hledej dd.mm.yyyy
    for ($i = 0; $i < 12 && $node; $i++) {
      $text = trim($node->textContent ?? '');
      if (preg_match('/\b(\d{2}\.\d{2}\.\d{4})\b/u', $text, $m)) {
        $dateStr = $m[1];
        break;
      }
      $node = $node->nextSibling;
    }

    // Parse datum
    $dt = null;
    if ($dateStr !== '') {
      $dt = DateTime::createFromFormat('d.m.Y', $dateStr, $TZ);
      if ($dt instanceof DateTime) {
        // stabilizuj čas (ať se nemění každým requestem)
        $dt->setTime(0, 0, 0);
      }
    }

    // Když se datum nepodaří najít, raději nepoužívej "now" (to dělá falešné novinky).
    // Použij velmi staré datum => MailerLite to nebude brát jako nové pořád dokola.
    if (!($dt instanceof DateTime)) {
      $dt = new DateTime('2000-01-01 00:00:00', $TZ);
    }

    // Pokus o perex: následující <p> (první odstavec po datu)
    $description = $title;
    $p = $xpath->query('following::p[1]', $h2);
    if ($p && $p->length > 0) {
      $desc = trim($p->item(0)->textContent ?? '');
      if ($desc !== '') {
        $description = $desc;
      }
    }

    $items[] = [
      'title'       => $title,
      'link'        => $href,
      'description' => $description,
      'pubDate'     => $dt->format(DATE_RSS),
      'guid'        => $href, // guid stabilně = URL článku
      'ts'          => $dt->getTimestamp(),
    ];
  }
}

// Když by se nenašlo nic, vrať validní RSS bez itemů
usort($items, fn($a, $b) => $b['ts'] <=> $a['ts']); // nejnovější první

$lastBuild = count($items) > 0 ? $items[0]['pubDate'] : (new DateTime('2000-01-01', $TZ))->format(DATE_RSS);

// ETag z obsahu (stabilní, když se nic nezmění)
$etagBase = $lastBuild . '|' . implode('|', array_map(fn($i) => $i['guid'] . ':' . $i['pubDate'], $items));
$etag = '"' . sha1($etagBase) . '"';

header('ETag: ' . $etag);
header('Cache-Control: public, max-age=300'); // 5 minut

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
  http_response_code(304);
  exit;
}

// Vygeneruj RSS XML
$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;

$rss = $xml->createElement('rss');
$rss->setAttribute('version', '2.0');

$channel = $xml->createElement('channel');
$channel->appendChild($xml->createElement('title', 'SVJ Kytlická Novinky'));
$channel->appendChild($xml->createElement('link', $SOURCE_URL));
$channel->appendChild($xml->createElement('description', 'Novinky zveřejněné na webu SVJ Kytlická'));
$channel->appendChild($xml->createElement('lastBuildDate', $lastBuild));

foreach ($items as $it) {
  $item = $xml->createElement('item');
  $item->appendChild($xml->createElement('title', $it['title']));
  $item->appendChild($xml->createElement('link', $it['link']));

  // Description jako CDATA (kvůli diakritice a znakům)
  $descEl = $xml->createElement('description');
  $descEl->appendChild($xml->createCDATASection($it['description']));
  $item->appendChild($descEl);

  $item->appendChild($xml->createElement('pubDate', $it['pubDate']));
  $item->appendChild($xml->createElement('guid', $it['guid']));

  $channel->appendChild($item);
}

$rss->appendChild($channel);
$xml->appendChild($rss);

echo $xml->saveXML();
