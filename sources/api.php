<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// YunoHost MySQL kimlik bilgilerini kontrol et
$db_access_file = __DIR__ . '/../db_access.txt';
$use_mysql = false;

if (file_exists($db_access_file)) {
    $lines = file($db_access_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $db_host = 'localhost';
    $db_name = '';
    $db_user = '';
    $db_pwd = '';
    
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            if ($key === 'db_host') $db_host = $val;
            if ($key === 'db_name') $db_name = $val;
            if ($key === 'db_user') $db_user = $val;
            if ($key === 'db_pwd') $db_pwd = $val;
        }
    }
    
    if (!empty($db_name) && !empty($db_user)) {
        $use_mysql = true;
    }
}

try {
    if ($use_mysql) {
        // 1. MySQL Bağlantısı (YunoHost'ta 'mysql' seçildiğinde otomatik devreye girer)
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $db = new PDO($dsn, $db_user, $db_pwd);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $db->exec("CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_hash VARCHAR(64) NOT NULL,
            iv VARCHAR(64) NOT NULL,
            ciphertext TEXT NOT NULL,
            created_at INT NOT NULL,
            INDEX idx_room_hash (room_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    } else {
        // 2. SQLite Bağlantısı (Yerel testlerde veya veritabanı 'none' seçildiğinde çalışır)
        $db_file = __DIR__ . '/chat.db';
        $config_file = __DIR__ . '/config.php';
        if (file_exists($config_file)) {
            include $config_file;
            if (isset($CUSTOM_DB_FILE)) {
                $db_file = $CUSTOM_DB_FILE;
            }
        }
        $db = new PDO("sqlite:" . $db_file);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $db->exec("CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            room_hash TEXT NOT NULL,
            iv TEXT NOT NULL,
            ciphertext TEXT NOT NULL,
            created_at INTEGER NOT NULL
        )");
        
        $db->exec("CREATE INDEX IF NOT EXISTS idx_room_hash ON messages(room_hash)");
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Veritabanı bağlantı hatası: " . $e->getMessage()]);
    exit;
}

// İstekleri Yönlendir
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $room_hash = $_GET['room'] ?? '';
    $since = intval($_GET['since'] ?? 0);

    if (empty($room_hash)) {
        http_response_code(400);
        echo json_encode(["error" => "Oda parametresi (room) eksik."]);
        exit;
    }

    try {
        $stmt = $db->prepare("SELECT id, iv, ciphertext, created_at FROM messages WHERE room_hash = :room_hash AND id > :since ORDER BY id ASC");
        $stmt->execute([
            ':room_hash' => $room_hash,
            ':since' => $since
        ]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(["messages" => $messages]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Mesajlar alınırken hata oluştu: " . $e->getMessage()]);
    }

} elseif ($method === 'POST') {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    $room_hash = $data['room_hash'] ?? '';
    $iv = $data['iv'] ?? '';
    $ciphertext = $data['ciphertext'] ?? '';

    if (empty($room_hash) || empty($iv) || empty($ciphertext)) {
        http_response_code(400);
        echo json_encode(["error" => "Eksik veri: room_hash, iv ve ciphertext zorunludur."]);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO messages (room_hash, iv, ciphertext, created_at) VALUES (:room_hash, :iv, :ciphertext, :created_at)");
        $stmt->execute([
            ':room_hash' => $room_hash,
            ':iv' => $iv,
            ':ciphertext' => $ciphertext,
            ':created_at' => time()
        ]);
        
        echo json_encode([
            "success" => true,
            "message_id" => $db->lastInsertId()
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => "Mesaj kaydedilirken hata oluştu: " . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(["error" => "Desteklenmeyen istek yöntemi."]);
}
