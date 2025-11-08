<?php
header("Content-Type: application/rss+xml; charset=UTF-8");

$listUrl = "https://kytlicka.eu/aktuality-a-oznameni/";

// 1) Načti seznam aktualit
$listHtml = @file_get_contents($listUrl);
if ($listHtml === false) {
    die("Nelze načíst seznam aktualit.");
}

libxml_use_internal_errors(true);
$listDom = new DOMDocument();
$listDom->loadHTML($listHtml);
$listXPath = new DOMXPath($listDom);

/**
 * Najdeme první link, který vede na detail článku.
 * Na Webnode bývají detaily ve tvaru ".../l/nejaky-clanek/".
 */
$linkNode = $listXPath->query("(//a[contains(@href, '/l/')])[1]")->item(0);

if (!$linkNode) {
    // Když nic nenajdeme, vrátíme prázdný, ale validní feed
    $title = "Žádné aktuality nebyly nalezeny";
    $link  = $listUrl;
    $pubDate = date(DATE_RSS);
} else {
    // Sestav URL článku
    $articleUrl = $linkNode->getAttribute("href");
    if (strpos($articleUrl, "http") !== 0) {
        $articleUrl = "https://kytlicka.eu" . $articleUrl;
    }

    // 2) Načti detail článku
    $articleHtml = @file_get_contents($articleUrl);
    if ($articleHtml === false) {
        // fallback - kdyby selhalo načtení článku
        $title = "Nelze načíst detail článku";
        $link  = $articleUrl;
        $pubDate = date(DATE_RSS);
    } else {
        $articleDom = new DOMDocument();
        $articleDom->loadHTML($articleHtml);
        $articleXPath = new DOMXPath($articleDom);

        // Titulek: vezmeme první <h1>
        $h1 = $articleXPath->query("//h1")->item(0);
        $title = $h1 ? trim($h1->textContent) : "Aktualita SVJ Kytlická";

        // Datum: zkusíme p s class obsahující 'date'
        $dateNode = $articleXPath->query(
            "//p[contains(@class,'date') or contains(@class,'blog-date')][1]"
        )->item(0);

        if ($dateNode) {
            $dateText = trim($dateNode->textContent);  // např. "05.11.2025"
            // sjednotit formát
            $normalized = str_replace(' ', '', $dateText);
            $normalized = str_replace('.', '-', $normalized);
            $timestamp = strtotime($normalized);
        } else {
            $timestamp = false;
        }

        // Když se datum nepodaří přečíst, radši vezmeme dnešek (ale to by se stávat nemělo)
        if ($timestamp === false || $timestamp <= 0) {
            $timestamp = time();
        }

        $pubDate = date(DATE_RSS, $timestamp);
        $link = $articleUrl;
    }
}

// 3) Postavíme RSS XML s jedním itemem

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
$item->appendChild($xml->createElement("title", $title));
$item->appendChild($xml->createElement("link", $link));
$item->appendChild($xml->createElement("description", $title));
$item->appendChild($xml->createElement("pubDate", $pubDate));
$item->appendChild($xml->createElement("guid", $link));

$channel->appendChild($item);
$rss->appendChild($channel);
$xml->appendChild($rss);

echo $xml->saveXML();
