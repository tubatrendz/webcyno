<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test 1: PHP Version</h2>";
echo "PHP: " . PHP_VERSION . "<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";

echo "<h2>Test 2: Database Connection</h2>";
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=tubatren_webcyno_db;charset=utf8mb4",
        "tubatren_webcyno_user",
        "Shaki15653@",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Database connected!<br><br>";
    
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<strong>Tables found: " . count($tables) . "</strong><br>";
    echo "<ul>";
    foreach ($tables as $t) echo "<li>$t</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "❌ <strong>Error:</strong> " . $e->getMessage();
}

echo "<h2>Test 3: Folders</h2>";
echo "logs folder: " . (is_dir(__DIR__ . '/logs') ? '✅ exists' : '❌ missing') . "<br>";
echo "assets folder: " . (is_dir(__DIR__ . '/assets') ? '✅ exists' : '❌ missing') . "<br>";
echo "assets/uploads: " . (is_dir(__DIR__ . '/assets/uploads') ? '✅ exists' : '❌ missing') . "<br>";

echo "<h2>Test 4: Config File</h2>";
$config = file_get_contents(__DIR__ . '/api/config.php');
if (strpos($config, 'YOUR_DATABASE_NAME') !== false) {
    echo "❌ config.php এ placeholder আছে (setup incomplete)<br>";
} else {
    echo "✅ config.php setup হয়েছে<br>";
}