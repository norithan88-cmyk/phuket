<?php
/**
 * Plugin Name: プーケットナビ PWA
 * Description: プーケットナビをAndroid・iPhoneのホーム画面に追加できるようにします。
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

add_action('init', function () {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

    if ($path === '/phuket-navi.webmanifest') {
        nocache_headers();
        header('Content-Type: application/manifest+json; charset=utf-8');
        echo wp_json_encode([
            'name' => 'プーケットナビ',
            'short_name' => 'プーケットナビ',
            'description' => 'タイ・プーケットの生活・観光サポートサイト',
            'start_url' => '/?source=app',
            'scope' => '/',
            'display' => 'standalone',
            'background_color' => '#f4faf7',
            'theme_color' => '#173c5a',
            'icons' => [
                ['src' => 'https://anjo-izumi.life/wp-content/uploads/2026/08/mion-radio-avatar.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => 'https://anjo-izumi.life/wp-content/uploads/2026/08/mion-radio-avatar.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable']
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($path === '/phuket-navi-sw.js') {
        nocache_headers();
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        echo "const CACHE='phuket-navi-v1';\n";
        echo "self.addEventListener('install',e=>{self.skipWaiting();e.waitUntil(caches.open(CACHE).then(c=>c.addAll(['/'])))});\n";
        echo "self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim()))});\n";
        echo "self.addEventListener('fetch',e=>{if(e.request.method!=='GET')return;e.respondWith(fetch(e.request).then(r=>{const x=r.clone();caches.open(CACHE).then(c=>c.put(e.request,x));return r}).catch(()=>caches.match(e.request).then(r=>r||caches.match('/'))))});\n";
        exit;
    }
}, 0);

add_action('wp_head', function () {
    echo '<link rel="manifest" href="/phuket-navi.webmanifest">' . "\n";
    echo '<meta name="theme-color" content="#173c5a">' . "\n";
    echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
    echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
    echo '<meta name="apple-mobile-web-app-title" content="プーケットナビ">' . "\n";
    echo '<link rel="apple-touch-icon" href="https://anjo-izumi.life/wp-content/uploads/2026/08/mion-radio-avatar.png">' . "\n";
    echo '<script>if("serviceWorker" in navigator){window.addEventListener("load",function(){navigator.serviceWorker.register("/phuket-navi-sw.js")})}</script>' . "\n";
});

/* 全ページ共通：実データと連動した最終更新表示 */
add_action('wp_footer', function () {
    if (is_admin()) return;
    $feeds = [
        'https://raw.githubusercontent.com/norithan88-cmyk/phuket/main/automation/air/air.json',
    ];
    ?>
    <style>
    #izuminavi-update-bar{background:#173f5f;color:#fff;text-align:center;font-size:13px;font-weight:700;letter-spacing:.03em;padding:9px 16px;line-height:1.5;min-height:38px;align-items:center;justify-content:center}
    #izuminavi-update-bar,#izuminavi-update-bar>span{color:#fff!important}
    #izuminavi-update-bar>span{position:relative;z-index:2}
    #izuminavi-update-bar .iz-update-dot{display:inline-block;width:8px;height:8px;margin-right:8px;border-radius:50%;background:#f5c453;box-shadow:0 0 0 4px rgba(245,196,83,.14)}
    @media(max-width:600px){#izuminavi-update-bar{display:flex!important;font-size:12px;padding:8px 10px}}
    </style>
    <script>
    (function () {
      var feeds = <?php echo wp_json_encode($feeds, JSON_UNESCAPED_SLASHES); ?>;
      function insertBar() {
        if (document.getElementById('izuminavi-update-bar')) return;
        var header = document.getElementById('header') || document.querySelector('header.l-header');
        if (!header) return;
        var bar = document.createElement('div');
        bar.id = 'izuminavi-update-bar';
        bar.setAttribute('role', 'status');
        bar.style.display = 'block';
        bar.style.setProperty('color', '#fff', 'important');
        bar.innerHTML = '<span class="iz-update-dot" aria-hidden="true"></span><span>PM2.5データ・更新時刻を確認中…</span>';
        var portal = document.getElementById('izuminavi-portal');
        var content = document.getElementById('content');
        if (portal) portal.insertBefore(bar, portal.firstChild);
        else if (content) content.insertBefore(bar, content.firstChild);
        else header.parentNode.insertBefore(bar, header.nextSibling);
        var label = bar.querySelector('span:last-child');
        label.style.setProperty('color', '#fff', 'important');
        Promise.all(feeds.map(function (url) {
          return fetch(url + '?status=' + Date.now(), {cache:'no-store'})
            .then(function (response) { if (!response.ok) throw new Error('fetch'); return response.json(); })
            .then(function (data) { return new Date(data.updated_at).getTime(); });
        })).then(function (times) {
          var valid = times.filter(function (time) { return Number.isFinite(time); });
          if (!valid.length) throw new Error('date');
          var updated = new Date(Math.min.apply(Math, valid));
          var now = new Date();
          var sameDay = updated.getFullYear() === now.getFullYear() ? (updated.getMonth() === now.getMonth() ? updated.getDate() === now.getDate() : false) : false;
          var time = updated.toLocaleTimeString('ja-JP', {hour:'2-digit', minute:'2-digit'});
          label.textContent = sameDay
            ? 'PM2.5データ・本日 ' + time + '更新'
            : 'PM2.5データ・' + updated.toLocaleDateString('ja-JP', {month:'numeric', day:'numeric'}) + ' ' + time + '更新';
        }).catch(function () {
          label.textContent = 'PM2.5データ・毎日自動更新';
        });
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', insertBar);
      else insertBar();
    })();
    </script>
    <?php
});

/* 「今日の3行まとめ」をサーバー側で実データに書き換える（検索エンジン向けに生HTMLを毎日更新） */
function phuket_navi_weather_label($code) {
    if ($code === 0) return '快晴';
    if ($code <= 2) return '晴れ時々くもり';
    if ($code === 3) return 'くもり';
    if ($code >= 45 && $code <= 48) return '霧';
    if (($code >= 51 && $code <= 67) || ($code >= 80 && $code <= 82)) return '雨';
    if (($code >= 71 && $code <= 77) || ($code >= 85 && $code <= 86)) return '雪';
    if ($code >= 95) return '雷雨';
    return '変わりやすい天気';
}

function phuket_navi_build_summary_lines() {
    $cache_key = 'phuket_navi_summary_lines_v1';
    $cached = get_transient($cache_key);
    if ($cached !== false) return $cached;

    $lines = [null, null, null];

    // ① 天気（Open-Meteo、プーケットタウンの座標）
    $weather = wp_remote_get('https://api.open-meteo.com/v1/forecast?latitude=7.8845&longitude=98.3913&daily=weather_code,temperature_2m_max,temperature_2m_min&timezone=Asia%2FBangkok&forecast_days=1', ['timeout' => 5]);
    if (!is_wp_error($weather)) {
        $body = json_decode(wp_remote_retrieve_body($weather), true);
        if (isset($body['daily']['weather_code'][0])) {
            $label = phuket_navi_weather_label((int) $body['daily']['weather_code'][0]);
            $high = round((float) $body['daily']['temperature_2m_max'][0]);
            $low = round((float) $body['daily']['temperature_2m_min'][0]);
            $lines[0] = 'プーケットは' . $label . '、最高' . $high . '度・最低' . $low . '度の予想です';
        }
    }

    // ② PM2.5（Air4Thai）
    $air = wp_remote_get('https://raw.githubusercontent.com/norithan88-cmyk/phuket/main/automation/air/air.json', ['timeout' => 5]);
    if (!is_wp_error($air)) {
        $body = json_decode(wp_remote_retrieve_body($air), true);
        if (!empty($body['stations'][0])) {
            $station = $body['stations'][0];
            $level = $station['aqi_level']['labelJa'] ?? null;
            if ($level) {
                $lines[1] = '今日の大気質（PM2.5）は、' . $station['label'] . 'で' . $level . 'です';
            }
        }
    }

    // ③ 固定案内（在住者向け情報＋観光情報の両方を案内）
    $lines[2] = '病院の日本語窓口情報・タイ語コピペ集などの生活情報と、ビーチ・観光スポット情報の両方をご案内しています';

    set_transient($cache_key, $lines, 20 * MINUTE_IN_SECONDS);
    return $lines;
}

add_action('template_redirect', function () {
    if (is_admin()) return;
    if (!is_front_page() && !is_home()) return;
    ob_start(function ($html) {
        $lines = phuket_navi_build_summary_lines();
        $ids = ['iz-sum-1', 'iz-sum-2', 'iz-sum-3'];
        foreach ($ids as $index => $id) {
            if (empty($lines[$index])) continue;
            $escaped = esc_html($lines[$index]);
            $html = preg_replace(
                '/(<li id="' . preg_quote($id, '/') . '">)[^<]*(<\/li>)/u',
                '$1' . str_replace('$', '\$', $escaped) . '$2',
                $html,
                1
            );
        }
        return $html;
    });
});
