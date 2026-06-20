<?php
// Önbelleklemeyi engelle (Her seferinde yeni karıştırılmış kod inmesi için)
header("Content-Type: application/javascript; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$source_file = __DIR__ . '/app.src.js';
if (!file_exists($source_file)) {
    $source_file = __DIR__ . '/../app.src.js';
}

if (!file_exists($source_file)) {
    http_response_code(404);
    echo "console.error('Kaynak kod dosyası (app.src.js) bulunamadı!');";
    exit;
}

$code = file_get_contents($source_file);

// ============================================================================
// 1. DİNAMİK METİN (STRING) GİZLEME VE YORUM TEMİZLEME
// ============================================================================

// Yorum satırlarını ve tırnaklı metin sabitlerini yakala
$pattern = '/\/\*[\s\S]*?\*\/|\/\/.*|"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'/';
preg_match_all($pattern, $code, $matches);

$unique_strings = [];
if (!empty($matches[0])) {
    foreach ($matches[0] as $m) {
        if (strpos($m, '/*') === 0 || strpos($m, '//') === 0) {
            continue;
        }
        if (!in_array($m, $unique_strings)) {
            $unique_strings[] = $m;
        }
    }
}

// Yorum satırlarını koddan temizle
$code = preg_replace_callback($pattern, function($match) {
    $val = $match[0];
    if (strpos($val, '/*') === 0 || strpos($val, '//') === 0) {
        return '';
    }
    return $val;
}, $code);

$strings = [];
$replacements = [];
$idx = 0;

foreach ($unique_strings as $str_literal) {
    $quote = $str_literal[0];
    $raw_str = substr($str_literal, 1, -1);
    
    // Kaçış karakterlerini PHP'de düzgünce çöz
    if ($quote === '"') {
        $decoded = json_decode('"' . $raw_str . '"');
    } else {
        $decoded = str_replace(array("\\'", "\\\\"), array("'", "\\"), $raw_str);
    }
    
    if ($decoded === null) {
        $decoded = $raw_str;
    }
    
    $strings[$idx] = $decoded;
    $replacements[$str_literal] = $idx;
    $idx++;
}

// Metin dizisi ve çözücü fonksiyon için rastgele isimler üret
$array_name = '_0x' . bin2hex(random_bytes(3));
$func_name = '_0x' . bin2hex(random_bytes(3));

// Uzun metinleri önce değiştirmek için sırala (substring çakışmalarını önlemek adına)
uksort($replacements, function($a, $b) {
    return strlen($b) - strlen($a);
});

// Kod içindeki metin sabitlerini fonksiyon çağrıları ile değiştir
foreach ($replacements as $literal => $index) {
    $code = str_replace($literal, $func_name . '(' . $index . ')', $code);
}

// ============================================================================
// 2. DİNAMİK DEĞİŞKEN VE FONKSİYON İSİMLERİ DEĞİŞTİRME (HEX RANDOMIZER)
// ============================================================================

$tokens_to_rename = [
    // Fonksiyonlar
    'sha256', 'deriveRoomKey', 'encryptData', 'decryptData', 'showToast', 

    'checkUrlHash', 'fetchMessages', 'startPolling', 'stopPolling', 'appendSystemMessage', 
    'appendMessage', 'appendMessageError', 'scrollToBottom', 'formatTime',
    
    // UI Değişkenleri
    'loginScreen', 'chatScreen', 'loginForm', 'usernameInput', 'keyInput', 
    'togglePasswordBtn', 'joinBtn', 'activeRoomNameLabel', 'activeRoomHashLabel', 'messagesContainer', 
    'messageForm', 'messageInput', 'leaveRoomBtn', 'shareLinkBtn', 'toast', 'toastIcon', 'toastText',
    
    // Durum ve Mantıksal Değişkenler
    'state', 'nickname', 'roomName', 'roomPassword', 'roomKey', 'roomHash', 
    'lastMessageId', 'pollInterval', 'isPolling', 'apiUrl', 'msgBuffer', 
    'hashBuffer', 'hashArray', 'decryptedBuffer', 'messagePayload', 'encrypted', 'processedAny'
];

$rename_map = [];
foreach ($tokens_to_rename as $token) {
    // Her istekte benzersiz 6 haneli rastgele hex isim üret (Örn: _0x3b8d2a)
    $rename_map[$token] = '_0x' . bin2hex(random_bytes(3));
}

// Kelime sınırları kullanarak tam eşleşen değişkenleri değiştir
foreach ($rename_map as $token => $random_hex) {
    $code = preg_replace('/\b' . preg_quote($token, '/') . '\b/', $random_hex, $code);
}

// ============================================================================
// 3. ÇIKTI ÜRETME
// ============================================================================

// Metin dizisini JSON formatında güvenle dışa aktar
$json_strings = json_encode($strings, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

echo "/**\n * CipherRoom Protected Runtime Engine\n * (C) 2026 CipherRoom Security System\n */\n\n";
echo "const {$array_name} = {$json_strings};\n";
echo "function {$func_name}(i) { return {$array_name}[i]; }\n\n";
echo $code;
