<?php
header("Content-Type: text/plain; charset=utf-8");
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== CipherRoom Veritabanı Teşhis ve Test Scripti ===\n\n";

$php_version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
echo "Aktif PHP Sürümü: " . PHP_VERSION . "\n";
echo "Sürücü Hatalarını Gidermek İçin SSH Komutları:\n";
echo "----------------------------------------------------------------------\n";
echo "sudo apt-get update\n";
echo "sudo apt-get install php" . $php_version . "-mysql php" . $php_version . "-sqlite3\n";
echo "sudo systemctl restart php" . $php_version . "-fpm\n";
echo "----------------------------------------------------------------------\n\n";

$db_access_file = __DIR__ . '/../db_access.txt';
echo "db_access.txt Kontrolü: ";
if (file_exists($db_access_file)) {
    echo "Mevcut. (MySQL Yapılandırması Aktif)\n";
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
    
    echo "MySQL Sunucu: $db_host\n";
    echo "MySQL Veritabanı Adı: $db_name\n";
    echo "MySQL Kullanıcı: $db_user\n";
    
    try {
        echo "\nMySQL Bağlantısı kuruluyor...";
        $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
        $db = new PDO($dsn, $db_user, $db_pwd);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo " BAŞARILI!\n";
        
        echo "Tablo oluşturma testi yapılıyor...";
        $db->exec("CREATE TABLE IF NOT EXISTS test_messages (id INT AUTO_INCREMENT PRIMARY KEY)");
        $db->exec("DROP TABLE test_messages");
        echo " BAŞARILI!\n";
        
        echo "\nSonuç: MySQL veritabanı sorunsuz çalışıyor.";
    } catch (PDOException $e) {
        echo " HATA ALINDI!\n";
        echo "Hata Detayı: " . $e->getMessage() . "\n";
    }
} else {
    echo "Mevcut Değil. (SQLite Yapılandırması Aktif)\n";
    $db_file = __DIR__ . '/chat.db';
    $config_file = __DIR__ . '/config.php';
    if (file_exists($config_file)) {
        include $config_file;
        if (isset($CUSTOM_DB_FILE)) {
            $db_file = $CUSTOM_DB_FILE;
        }
    }
    echo "SQLite Veritabanı Yolu: $db_file\n";
    $db_dir = dirname($db_file);
    echo "Veritabanı klasörü ($db_dir) yazılabilir mi?: " . (is_writable($db_dir) ? "EVET" : "HAYIR") . "\n";
    
    if (file_exists($db_file)) {
        echo "chat.db dosyası mevcut. Yazılabilir mi?: " . (is_writable($db_file) ? "EVET" : "HAYIR") . "\n";
    } else {
        echo "chat.db dosyası henüz oluşturulmamış.\n";
    }
    
    try {
        echo "\nSQLite Bağlantısı kuruluyor...";
        $db = new PDO("sqlite:" . $db_file);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo " BAŞARILI!\n";
        
        echo "Tablo oluşturma testi yapılıyor...";
        $db->exec("CREATE TABLE IF NOT EXISTS test_messages (id INTEGER PRIMARY KEY)");
        $db->exec("DROP TABLE test_messages");
        echo " BAŞARILI!\n";
        
        echo "\nSonuç: SQLite veritabanı sorunsuz çalışıyor.";
    } catch (PDOException $e) {
        echo " HATA ALINDI!\n";
        echo "Hata Detayı: " . $e->getMessage() . "\n";
    }
}
