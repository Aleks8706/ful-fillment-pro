<?php
/**
 * api.php — Бэкенд для Фулфилмент Про v13.0
 * Поддерживает: SQLite (авто) / MySQL (опционально)
 * Эндпоинты: GET ?action=get | POST ?action=save
 */

// === CORS & HEADERS ===
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// === ПОДКЛЮЧЕНИЕ К БД (SQLite по умолчанию) ===
$dbFile = __DIR__ . '/warehouse.db';
try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Для MySQL раскомментируйте блок ниже и закомментируйте SQLite
    /*
    $db = new PDO('mysql:host=localhost;dbname=warehouse_db;charset=utf8mb4', 'user', 'pass');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    */
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]);
    exit;
}

// === АВТОСОЗДАНИЕ ТАБЛИЦ ===
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
$isSqlite = ($driver === 'sqlite');

$queries = [
    "CREATE TABLE IF NOT EXISTS employees (id INTEGER PRIMARY KEY, name TEXT, role TEXT, rate INTEGER)",
    "CREATE TABLE IF NOT EXISTS products (id INTEGER PRIMARY KEY, sku TEXT, bar TEXT, nm TEXT, cat TEXT, loc TEXT, exp TEXT, q INTEGER, w REAL, pr REAL)",
    "CREATE TABLE IF NOT EXISTS timeRecords (id INTEGER PRIMARY KEY " . ($isSqlite ? "AUTOINCREMENT" : "AUTO_INCREMENT") . ", date TEXT, eid INTEGER, checkIn TEXT, checkOut TEXT)",
    "CREATE TABLE IF NOT EXISTS fines (id INTEGER PRIMARY KEY " . ($isSqlite ? "AUTOINCREMENT" : "AUTO_INCREMENT") . ", date TEXT, eid INTEGER, rsn TEXT, am INTEGER, com TEXT, st TEXT)",
    "CREATE TABLE IF NOT EXISTS receivings (id INTEGER PRIMARY KEY, n TEXT, d TEXT, s TEXT, sku TEXT, pid INTEGER, q INTEGER, st TEXT)",
    "CREATE TABLE IF NOT EXISTS shippings (id INTEGER PRIMARY KEY, n TEXT, d TEXT, c TEXT, sku TEXT, pid INTEGER, q INTEGER, st TEXT)",
    "CREATE TABLE IF NOT EXISTS apiKeys (id INTEGER PRIMARY KEY, client TEXT, key TEXT, status TEXT)",
    "CREATE TABLE IF NOT EXISTS metadata (key TEXT PRIMARY KEY, value TEXT)"
];

foreach ($queries as $q) {
    try { $db->exec($q); } catch (PDOException $e) { /* ignore if exists */ }
}

// === ВСПОМОГАТЕЛЬНАЯ ФУНКЦИЯ СИНХРОНИЗАЦИИ ===
function syncTable($db, $table, $data, $fields) {
    $db->exec("DELETE FROM $table");
    if (empty($data)) return;
    
    $cols = implode(',', $fields);
    $placeholders = implode(',', array_fill(0, count($fields), '?'));
    $stmt = $db->prepare("INSERT INTO $table ($cols) VALUES ($placeholders)");
    
    foreach ($data as $row) {
        $values = [];
        foreach ($fields as $f) {
            $val = $row[$f] ?? null;
            $values[] = ($val === '' || $val === 'null' || $val === 'undefined') ? null : $val;
        }
        $stmt->execute($values);
    }
}

// === ОБРАБОТКА ЗАПРОСОВ ===
$action = $_GET['action'] ?? '';

// 🔹 GET: Получить все данные
if ($action === 'get') {
    try {
        $metaStmt = $db->prepare("SELECT value FROM metadata WHERE key = ?");
        
        $metaStmt->execute(['nextEmpId']);
        $nextEmp = $metaStmt->fetchColumn() ?: 1;
        $metaStmt->execute(['nextProdId']);
        $nextProd = $metaStmt->fetchColumn() ?: 1;

        $data = [
            'employees'   => $db->query("SELECT * FROM employees")->fetchAll(),
            'products'    => $db->query("SELECT * FROM products")->fetchAll(),
            'timeRecords' => $db->query("SELECT * FROM timeRecords")->fetchAll(),
            'fines'       => $db->query("SELECT * FROM fines")->fetchAll(),
            'receivings'  => $db->query("SELECT * FROM receivings")->fetchAll(),
            'shippings'   => $db->query("SELECT * FROM shippings")->fetchAll(),
            'apiKeys'     => $db->query("SELECT * FROM apiKeys")->fetchAll(),
            'nextEmpId'   => (int)$nextEmp,
            'nextProdId'  => (int)$nextProd
        ];
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Fetch failed: ' . $e->getMessage()]);
    }
    exit;
}

// 🔹 POST: Сохранить все данные
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON payload']);
            exit;
        }

        $db->beginTransaction();

        syncTable($db, 'employees',   $input['employees']   ?? [], ['id','name','role','rate']);
        syncTable($db, 'products',    $input['products']    ?? [], ['id','sku','bar','nm','cat','loc','exp','q','w','pr']);
        syncTable($db, 'timeRecords', $input['timeRecords'] ?? [], ['id','date','eid','checkIn','checkOut']);
        syncTable($db, 'fines',       $input['fines']       ?? [], ['id','date','eid','rsn','am','com','st']);
        syncTable($db, 'receivings',  $input['receivings']  ?? [], ['id','n','d','s','sku','pid','q','st']);
        syncTable($db, 'shippings',   $input['shippings']   ?? [], ['id','n','d','c','sku','pid','q','st']);
        syncTable($db, 'apiKeys',     $input['apiKeys']     ?? [], ['id','client','key','status']);

        $metaStmt = $db->prepare("INSERT OR REPLACE INTO metadata (key, value) VALUES (?, ?)");
        if (isset($input['nextEmpId']))  $metaStmt->execute(['nextEmpId',  $input['nextEmpId']]);
        if (isset($input['nextProdId'])) $metaStmt->execute(['nextProdId', $input['nextProdId']]);

        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Data saved successfully', 'timestamp' => date('c')]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Save failed: ' . $e->getMessage()]);
    }
    exit;
}

// 🔹 Fallback
http_response_code(400);
echo json_encode(['error' => 'Unknown action. Use ?action=get or ?action=save']);
?>
