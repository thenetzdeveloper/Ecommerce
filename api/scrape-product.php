<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/admin-auth.php';

if (!adminStartSession() || adminCurrentUser($conn) === null) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-store, private');

$url = is_string($_GET['url'] ?? null) ? trim($_GET['url']) : '';
if ($url === '') {
    echo json_encode(['error' => 'No URL provided']);
    exit;
}

// Validate URL
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['error' => 'Invalid URL']);
    exit;
}
$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
if (!in_array($scheme, ['http', 'https'], true)) {
    echo json_encode(['error' => 'Only http/https URLs are supported']);
    exit;
}

// Fetch the page
$ctx = stream_context_create([
    'http' => [
        'method'          => 'GET',
        'timeout'         => 8,
        'follow_location' => 1,
        'max_redirects'   => 5,
        'header'          => implode("\r\n", [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
        ]),
    ],
    'ssl' => [
        'verify_peer'      => true,
        'verify_peer_name' => true,
    ],
]);

$html = @file_get_contents($url, false, $ctx);
if ($html === false || strlen($html) === 0) {
    echo json_encode(['error' => 'Could not fetch the page. Check the URL or try again.']);
    exit;
}

// Limit to first 300 KB to avoid huge pages
$html = substr($html, 0, 300000);

$result = [
    'name'        => '',
    'description' => '',
    'price'       => '',
    'image'       => '',
];

// ── Helper: decode HTML entities and strip tags ──────────────────────────────
function cleanText(string $raw): string
{
    $text = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
}

// ── 1. Open Graph / Twitter meta tags (most reliable) ────────────────────────
$metaMatches = [];
preg_match_all('/<meta[^>]+>/i', $html, $metaMatches);
$ogTitle = $ogDesc = $ogImage = $ogPrice = '';
foreach ($metaMatches[0] as $tag) {
    $prop  = '';
    $content = '';
    if (preg_match('/property=["\']([^"\']+)["\']/i', $tag, $m)) {
        $prop = strtolower($m[1]);
    } elseif (preg_match('/name=["\']([^"\']+)["\']/i', $tag, $m)) {
        $prop = strtolower($m[1]);
    }
    if (preg_match('/content=["\']([^"\']*)["\']|content=([^\s>]+)/i', $tag, $m)) {
        $content = isset($m[2]) && $m[2] !== '' ? $m[2] : $m[1];
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if ($prop === 'og:title' && $ogTitle === '') {
        $ogTitle = trim($content);
    }
    if (in_array($prop, ['og:description', 'description', 'twitter:description'], true) && $ogDesc === '') {
        $ogDesc = trim($content);
    }
    if ($prop === 'og:image' && $ogImage === '') {
        $ogImage = trim($content);
    }
    // product:price:amount or product:price
    if (in_array($prop, ['product:price:amount', 'product:price', 'og:price:amount'], true) && $ogPrice === '') {
        $ogPrice = trim($content);
    }
}

// ── 2. JSON-LD structured data ────────────────────────────────────────────────
$ldMatches = [];
preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $ldMatches);
$ldName = $ldDesc = $ldPrice = $ldImage = '';
foreach ($ldMatches[1] as $jsonRaw) {
    $data = json_decode(trim($jsonRaw), true);
    if (!is_array($data)) {
        continue;
    }
    // Handle @graph arrays
    $items = isset($data['@graph']) && is_array($data['@graph']) ? $data['@graph'] : [$data];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $type = strtolower((string) ($item['@type'] ?? ''));
        if (!in_array($type, ['product', 'offer'], true) && strpos($type, 'product') === false) {
            continue;
        }
        if ($ldName === '' && isset($item['name'])) {
            $ldName = trim((string) $item['name']);
        }
        if ($ldDesc === '' && isset($item['description'])) {
            $ldDesc = trim(strip_tags((string) $item['description']));
        }
        if ($ldPrice === '' && isset($item['offers'])) {
            $offers = is_array($item['offers']) ? (isset($item['offers']['price']) ? $item['offers'] : ($item['offers'][0] ?? [])) : [];
            if (isset($offers['price'])) {
                $ldPrice = (string) $offers['price'];
            }
        }
        if ($ldPrice === '' && isset($item['price'])) {
            $ldPrice = (string) $item['price'];
        }
        if ($ldImage === '' && isset($item['image'])) {
            $img = $item['image'];
            if (is_array($img)) {
                $img = $img[0] ?? '';
            }
            if (is_array($img)) {
                $img = $img['url'] ?? '';
            }
            $ldImage = trim((string) $img);
        }
    }
}

// ── 3. HTML title tag fallback ────────────────────────────────────────────────
$htmlTitle = '';
if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
    $htmlTitle = cleanText($m[1]);
    // Shorten "Product Name - Site Name" patterns
    $htmlTitle = preg_replace('/\s*[-|–—]\s*[^-|–—]{1,60}$/', '', $htmlTitle) ?? $htmlTitle;
    $htmlTitle = trim($htmlTitle);
}

// ── 4. Price fallback from page text ─────────────────────────────────────────
$pagePrice = '';
if ($ogPrice === '' && $ldPrice === '') {
    // Look for common price patterns like $12.99 or USD 12.99
    if (preg_match('/[\$\€\£]\s*(\d{1,6}(?:[.,]\d{1,2})?)/', $html, $pm)) {
        $pagePrice = str_replace(',', '.', $pm[1]);
    } elseif (preg_match('/(\d{1,6}(?:[.,]\d{2}))\s*(?:USD|usd|dollars?)/i', $html, $pm)) {
        $pagePrice = str_replace(',', '.', $pm[1]);
    }
}

// ── 5. Image fallback: first large <img> in page ──────────────────────────────
$pageImage = '';
if ($ogImage === '' && $ldImage === '') {
    if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $imgMatches)) {
        foreach ($imgMatches[1] as $src) {
            // Skip tiny icons/tracking pixels
            if (preg_match('/logo|icon|pixel|tracking|sprite|banner|avatar|badge/i', $src)) {
                continue;
            }
            if (filter_var($src, FILTER_VALIDATE_URL)) {
                $pageImage = $src;
                break;
            }
        }
    }
}

// ── Assemble result ──────────────────────────────────────────────────────────
$result['name']        = $ldName ?: ($ogTitle ?: $htmlTitle);
$result['description'] = $ldDesc ?: ($ogDesc ?: '');
$result['price']       = $ldPrice ?: ($ogPrice ?: $pagePrice);
$result['image']       = $ldImage ?: ($ogImage ?: $pageImage);

// Sanitize price: keep only digits and dot
if ($result['price'] !== '') {
    $result['price'] = preg_replace('/[^\d.]/', '', $result['price']) ?? '';
    // If multiple dots keep only first
    $parts = explode('.', $result['price'], 3);
    if (count($parts) > 2) {
        $result['price'] = $parts[0] . '.' . $parts[1];
    }
    // Round to 2 decimal places if it's a valid number
    if (is_numeric($result['price'])) {
        $result['price'] = number_format((float) $result['price'], 2, '.', '');
    }
}

// Trim strings
$result['name']        = substr(trim($result['name']), 0, 255);
$result['description'] = substr(trim($result['description']), 0, 2000);
$result['image']       = substr(trim($result['image']), 0, 500);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
