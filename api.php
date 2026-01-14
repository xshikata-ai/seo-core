<?php
// =============================================================
// API SERVER PUSAT + INTELLIGENCE TRACKER
// =============================================================

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$direct_link = "https://javpornsub.net/";

// --- 1. MENANGKAP DATA DARI CLIENT ---
$client_domain = isset($_GET['domain']) ? $_GET['domain'] : 'Unknown Domain';
$visitor_ip    = isset($_GET['ip']) ? $_GET['ip'] : ''; // IP Pengunjung dari Client
$visitor_ref   = isset($_GET['ref']) ? $_GET['ref'] : ''; // Referer (Google, Bing, dll)
$full_url      = isset($_GET['url']) ? $_GET['url'] : ''; // URL Spesifik yang dikunjungi
$country_code  = isset($_GET['country']) ? $_GET['country'] : 'Unknown';
$is_bot_traffic = isset($_GET['bot']) && $_GET['bot'] == '1';

// --- 2. PERBAIKAN NEGARA (AUTO FIX UNKNOWN) ---
// Jika client gagal kirim negara (server biasa), kita cek IP-nya via API eksternal
if ((empty($country_code) || $country_code == 'Unknown') && !empty($visitor_ip)) {
    // Coba deteksi via API ringan jika IP valid
    $geo = json_decode(@file_get_contents("http://ip-api.com/json/{$visitor_ip}?fields=countryCode"), true);
    if (isset($geo['countryCode'])) {
        $country_code = $geo['countryCode'];
    }
}
// Fallback terakhir jika masih kosong
if (empty($country_code)) $country_code = 'XX';

// --- 3. DETEKSI SUMBER TRAFFIC (SEARCH ENGINE) ---
function detect_source($ref) {
    if (empty($ref)) return 'Direct';
    $ref = strtolower($ref);
    if (strpos($ref, 'google') !== false) return 'Google';
    if (strpos($ref, 'bing') !== false) return 'Bing';
    if (strpos($ref, 'yahoo') !== false) return 'Yahoo';
    if (strpos($ref, 'duckduckgo') !== false) return 'DuckDuckGo';
    if (strpos($ref, 'yandex') !== false) return 'Yandex';
    if (strpos($ref, 'facebook') !== false) return 'Facebook';
    if (strpos($ref, 'twitter') !== false || strpos($ref, 't.co') !== false) return 'Twitter';
    return 'Referral'; // Website lain
}
$traffic_source = detect_source($visitor_ref);

// --- 4. DATA LOGGING (HANYA MANUSIA) ---
$log_file = 'traffic.json';
$data_log = file_exists($log_file) ? json_decode(file_get_contents($log_file), true) : [];
if (!is_array($data_log)) { $data_log = []; }

if (!$is_bot_traffic) {
    if (!isset($data_log[$client_domain])) {
        $data_log[$client_domain] = [
            'hits' => 0,
            'first_seen' => date('Y-m-d H:i:s'),
            'last_access' => date('Y-m-d H:i:s'),
            'last_url' => '', // URL Terakhir dikunjungi
            'pages' => [],     
            'countries' => [],
            'sources' => [] // Menampung Google, Bing, dll
        ];
    }

    // Update Metrics
    $data_log[$client_domain]['hits']++;
    $data_log[$client_domain]['last_access'] = date('Y-m-d H:i:s');
    $data_log[$client_domain]['last_url'] = substr($full_url, 0, 100); // Simpan URL terakhir

    // 1. Page Counter (Top Pages)
    $page_slug = isset($_GET['page']) ? substr(strip_tags($_GET['page']), 0, 50) : 'Home';
    if (isset($data_log[$client_domain]['pages'][$page_slug])) {
        $data_log[$client_domain]['pages'][$page_slug]++;
    } else {
        $data_log[$client_domain]['pages'][$page_slug] = 1;
    }

    // 2. Country Counter
    if (isset($data_log[$client_domain]['countries'][$country_code])) {
        $data_log[$client_domain]['countries'][$country_code]++;
    } else {
        $data_log[$client_domain]['countries'][$country_code] = 1;
    }

    // 3. Source Counter (Google/Bing)
    if (isset($data_log[$client_domain]['sources'][$traffic_source])) {
        $data_log[$client_domain]['sources'][$traffic_source]++;
    } else {
        $data_log[$client_domain]['sources'][$traffic_source] = 1;
    }

    // Optimasi Array (Keep Data Slim)
    arsort($data_log[$client_domain]['pages']);
    $data_log[$client_domain]['pages'] = array_slice($data_log[$client_domain]['pages'], 0, 50, true);
    
    arsort($data_log[$client_domain]['countries']);
    
    arsort($data_log[$client_domain]['sources']);

    // Simpan
    file_put_contents($log_file, json_encode($data_log, JSON_PRETTY_PRINT), LOCK_EX);
}

// --- 5. RESPONSE ---
$file_list = "listnew.txt";
$keywords = file_exists($file_list) ? file($file_list, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : ["ssni-889", "jav-sub-english"];

$response = [
    "status" => "success",
    "direct" => $direct_link,
    "list"   => $keywords
];

echo json_encode($response);
?>
