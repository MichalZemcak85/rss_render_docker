<?php
header("Content-Type: application/rss+xml; charset=UTF-8");

$listUrl = "https://kytlicka.eu/aktuality-a-oznameni/";

// 1) Načtení stránky s přehledem aktualit
$listHtml = @file_get_contents($listUrl);
if ($listHtml === false) {
    // Bez přehledu neumíme nic – vrátíme prázdné RSS
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<rss version=\"2.0\"><channel>";
    echo "<title>SVJ Kytlická Novinky</title>";
    echo "<link>{$listUrl}</link>";
    echo "<description>Nelze načíst stránku s aktualitami.</description>";
    echo "</channel></rss>";
    exit;
}

libxml_use_internal_errors(true);
$listDom = new DOMDocument();
$listDom->loadHTML($listHtml);
$listXPath = new DOMXPath($listDom);

// 2) Všechny odkazy na články /l/...
$linkNodes = $listXPath->query("//a[contains(@href, '/l/')]");
$seen = [];
$articleUrls = [];

foreach ($linkNodes as $node) {
    $href = $node->getAttribute("href");
    if (!$href) continue;

    // relativní → absolutní
    if (strpos($href, "http") !== 0) {
        $href = "https://kytlicka.eu" . $href;
    }

    // unikátní
    $key = parse_url($href, PHP_URL_PATH);
    if ($key && !isset($seen[$key])) {
        $seen[$key] = true;
        $articleUrls[] = $href;
    }
}

// Kdyby náhodou nic
if (empty($articleUrls)) {
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<rss version=\"2.0\"><channel>";
    echo "<title>SVJ Kytlická Novinky</title>";
    echo "<link>{$listUrl}</link>";
    echo "<description>Našli jsme 0 článků.</description>";
    echo "</channel></rss>";
    exit;
}

// 3) Projít články, najít nejnovější podle data
$latest = [
    'ts' => 0,
    'title' => null,
    'link' => null,
];

foreach ($articleUrls as $url) {
    $articleHtml = @file_get_contents($url);
    if ($articleHtml === false) {
        continue;
    }

    $articleDom = new DOMDocument();
    $articleDom->loadHTML($articleHtml);
    $articleXPath = new DOMXPath($articleDom);

    // Titulek z <h1>
    $h1 = $articleXPath->query("//h1")->item(0);
    $title = $h1 ? trim($h1->textContent) : "Aktualita SVJ Kytlická";

    // Datum – hledej odstavec s class obsahující "date" nebo "blog-date"
    $dateNode = $articleXPath->query("//p[contains(@class,'date') or contains(@class,'blog-date')]")->item(0);
    $timestamp = 0;

    if ($dateNode) {
        $raw = trim($dateNode->textContent);
        // vytáhnout vzor dd.mm.yyyy (ignoruje text okolo)
        if (preg_match('/(\d{1,2}\.\s*\d{1,2}\.\s*\d{4})/', $raw, $m)) {
            $dateStr = preg_replace('/\s+/', '', $m[1]); // 05.11.2025 nebo 5.11.2025
            $dt = DateTime::createFromFormat('j.m.Y', $dateStr);
            if ($dt) {
                $timestamp = $dt->getTimestamp();
            }
        }
    }

    // Pokud se nepodařilo, bereme 0 → takový článek nikdy nevyhraje
    if ($timestamp === 0) {
        continue;
    }

    if ($timestamp > $latest['ts']) {
        $latest['ts'] = $timestamp;
        $latest['title'] = $title;
        $latest['link'] = $url;
    }
}

// 4) Pokud jsme nic platného nenašli, fallback na první URL
if ($latest['ts'] === 0 || !$latest['title'] || !$latest['link']) {
    $fallback = $articleUrls[0];
    $latest['ts'] = time();
    $latest['title'] = "Aktualita SVJ Kytlická";
    $latest['link'] = $fallback;
}

// 5) Připrav RSS s tím nejnovějším
$pubDate = date(DATE_RSS, $latest['ts']);

$xml = new DOMDocument("1.0", "UTF-8");
$xml->formatOutput = true;

$rss = $xml->createElement("rss");
$rss->setAttribute("version", "2.0");

$channel = $xml->createElement("channel");
$channel->appendChild($xml->createElement("title", "SVJ Kytlická Novinky"));
$channel->appendChild($xml->createElement("link", $listUrl));
$channel->appendChild($xml->createElement("description", "Novinky zveřejněné na webu SVJ Kytlická"));
$channel->appendChild($xml->createElement("lastBuildDate", $pubDate));

$item = $xml->createElement("item");
$item->appendChild($xml->createElement("title", htmlspecialchars($latest['title'], ENT_XML1)));
$item->appendChild($xml->createElement("link", htmlspecialchars($latest['link'], ENT_XML1)));
$item->appendChild($xml->createElement("description", htmlspecialchars($latest['title'], ENT_XML1)));
$item->appendChild($xml->createElement("pubDate", $pubDate));
$item->appendChild($xml->createElement("guid", htmlspecialchars($latest['link'], ENT_XML1)));

$channel->appendChild($item);
$rss->appendChild($channel);
$xml->appendChild($rss);

echo $xml->saveXML();
