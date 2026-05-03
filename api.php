<?php
/**
 * api.php — Бэкенд для системы "Фулфилмент Про"
 * 
 * Поддерживает:
 * - SQLite (по умолчанию, не требует настройки)
 * - MySQL (раскомментируйте блок ниже)
 * 
 * Эндпоинты:
 * - GET  /api.php?action=get     — получить все данные
 * - POST /api.php?action=save    — сохранить все данные
 * 
 * Формат данных: JSON
 */

// === НАСТРОЙКИ ===
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Обработка preflight-запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// === ПОДКЛЮЧЕНИЕ К БАЗЕ ДАННЫХ ===

// 🔹 ВАРИАНТ 1: SQLite (рекомендуется для начала)
// База создастся автоматически в файле warehouse.db
$dbFile = __DIR__ . '/warehouse.db';
try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'SQLite connection failed: ' . $e->getMessage()]);
    exit;
}

// 🔹 ВАРИАНТ 2: MySQL (раскомментируйте для продакшена)
/*
$host = 'localhost';
$dbname = 'warehouse_db';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $db = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'MySQL connection failed: ' . $e->getMessage()]);
    exit;
}
*/

// === СОЗДАНИЕ ТАБЛИЦ (если не существуют) ===
// Для SQLite
if (strpos($db->getAttribute(PDO::ATTR_DRIVER_NAME), 'sqlite') === 0) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS employees (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            role TEXT NOT NULL,
            rate INTEGER DEFAULT 500,
            color TEXT DEFAULT '#6366f1'
        )
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY,
            sku TEXT NOT NULL UNIQUE,
            barcode TEXT,
            name TEXT NOT NULL,
            category TEXT,
            location TEXT,
            qty INTEGER DEFAULT 0,
            weight REAL DEFAULT 0,
            expiry TEXT,
            cost REAL DEFAULT 0,
            price REAL DEFAULT 0,
            desc TEXT
        )
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS time_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date TEXT NOT NULL,
            employee_id INTEGER NOT NULL,
            check_in TEXT,
            check_out TEXT,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        )
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS fines (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date TEXT NOT NULL,
            employee_id INTEGER NOT NULL,
            reason TEXT NOT NULL,
            amount INTEGER NOT NULL,
            comment TEXT,
            status TEXT DEFAULT 'Активен',
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
        )
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS receivings (
            id INTEGER PRIMARY KEY,
            number TEXT NOT NULL,
            date TEXT NOT NULL,
            supplier TEXT,
            invoice TEXT,
            comment TEXT,
            product_id INTEGER,
            qty INTEGER DEFAULT 0,
            status TEXT DEFAULT 'pending',
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
        )
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS shippings (
            id INTEGER PRIMARY KEY,
            number TEXT NOT NULL,
            date TEXT NOT NULL,
            client TEXT,
            order_num TEXT,
            address TEXT,
            product_id INTEGER,
            qty INTEGER DEFAULT 0,
            status TEXT DEFAULT 'pending',
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
        )
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS api_keys (
            id INTEGER PRIMARY KEY,
            client TEXT NOT NULL,
            key TEXT NOT NULL,
            status TEXT DEFAULT 'active'
        )
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS metadata (
            key TEXT PRIMARY KEY,
            value TEXT
        )
    ");
    
} 
// Для MySQL
else {
    $db->exec("
        CREATE TABLE IF NOT EXISTS employees (
            id INT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            role VARCHAR(100) NOT NULL,
            rate INT DEFAULT 500,
            color VARCHAR(7) DEFAULT '#6366f1'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT PRIMARY KEY,
            sku VARCHAR(100) NOT NULL UNIQUE,
            barcode VARCHAR(100),
            name VARCHAR(255) NOT NULL,
            category VARCHAR(100),
            location VARCHAR(100),
            qty INT DEFAULT 0,
            weight DECIMAL(10,3) DEFAULT 0,
            expiry DATE,
            cost DECIMAL(10,2) DEFAULT 0,
            price DECIMAL(10,2) DEFAULT 0,
            desc TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS time_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            employee_id INT NOT NULL,
            check_in TIME,
            check_out TIME,
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            INDEX idx_date_emp (date, employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS fines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            employee_id INT NOT NULL,
            reason VARCHAR(255) NOT NULL,
            amount INT NOT NULL,
            comment TEXT,
            status VARCHAR(50) DEFAULT 'Активен',
            FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
            INDEX idx_date_emp (date, employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS receivings (
            id INT PRIMARY KEY,
            number VARCHAR(50) NOT NULL,
            date DATETIME NOT NULL,
            supplier VARCHAR(255),
            invoice VARCHAR(100),
            comment TEXT,
            product_id INT,
            qty INT DEFAULT 0,
            status VARCHAR(50) DEFAULT 'pending',
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
            INDEX idx_date (date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS shippings (
            id INT PRIMARY KEY,
            number VARCHAR(50) NOT NULL,
            date DATETIME NOT NULL,
            client VARCHAR(255),
            order_num VARCHAR(100),
            address TEXT,
            product_id INT,
            qty INT DEFAULT 0,
            status VARCHAR(50) DEFAULT 'pending',
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
            INDEX idx_date (date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS api_keys (
            id INT PRIMARY KEY,
            client VARCHAR(255) NOT NULL,
            key VARCHAR(255) NOT NULL,
            status VARCHAR(50) DEFAULT 'active'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS metadata (
            `key` VARCHAR(100) PRIMARY KEY,
            value TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// === ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ===

/**
 * Получить значение из metadata
 */
function getMeta($db, $key, $default = null) {
    $stmt = $db->prepare("SELECT value FROM metadata WHERE key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetchColumn();
    return $result !== false ? $result : $default;
}

/**
 * Сохранить значение в metadata
 */
function setMeta($db, $key, $value) {
    $stmt = $db->prepare("INSERT OR REPLACE INTO metadata (key, value) VALUES (?, ?)");
    return $stmt->execute([$key, $value]);
}

/**
 * Получить все данные для экспорта
 */
function getAllData($db) {
    return [
        'employees' => $db->query("SELECT * FROM employees")->fetchAll(),
        'products' => $db->query("SELECT * FROM products")->fetchAll(),
        'timeRecords' => $db->query("SELECT * FROM time_records")->fetchAll(),
        'fines' => $db->query("SELECT * FROM fines")->fetchAll(),
        'receivings' => $db->query("SELECT * FROM receivings")->fetchAll(),
        'shippings' => $db->query("SELECT * FROM shippings")->fetchAll(),
        'apiKeys' => $db->query("SELECT * FROM api_keys")->fetchAll(),
        'nextEmpId' => (int)(getMeta($db, 'nextEmpId', 1)),
        'nextProdId' => (int)(getMeta($db, 'nextProdId', 1)),
        'exportDate' => date('c')
    ];
}

/**
 * Сохранить все данные (полная синхронизация)
 */
function saveAllData($db, $data) {
    try {
        $db->beginTransaction();
        
        // === СОТРУДНИКИ ===
        $db->exec("DELETE FROM employees");
        if (!empty($data['employees'])) {
            $stmt = $db->prepare("
                INSERT INTO employees (id, name, role, rate, color) 
                VALUES (:id, :name, :role, :rate, :color)
            ");
            foreach ($data['employees'] as $emp) {
                $stmt->execute([
                    ':id' => $emp['id'],
                    ':name' => $emp['name'],
                    ':role' => $emp['role'],
                    ':rate' => $emp['rate'] ?? 500,
                    ':color' => $emp['color'] ?? '#6366f1'
                ]);
            }
        }
        
        // === ТОВАРЫ ===
        $db->exec("DELETE FROM products");
        if (!empty($data['products'])) {
            $stmt = $db->prepare("
                INSERT INTO products (id, sku, barcode, name, category, location, qty, weight, expiry, cost, price, desc) 
                VALUES (:id, :sku, :barcode, :name, :category, :location, :qty, :weight, :expiry, :cost, :price, :desc)
            ");
            foreach ($data['products'] as $prod) {
                $stmt->execute([
                    ':id' => $prod['id'],
                    ':sku' => $prod['sku'],
                    ':barcode' => $prod['barcode'] ?? null,
                    ':name' => $prod['name'],
                    ':category' => $prod['category'] ?? null,
                    ':location' => $prod['location'] ?? null,
                    ':qty' => $prod['qty'] ?? 0,
                    ':weight' => $prod['weight'] ?? 0,
                    ':expiry' => $prod['expiry'] ?? null,
                    ':cost' => $prod['cost'] ?? 0,
                    ':price' => $prod['price'] ?? 0,
                    ':desc' => $prod['desc'] ?? null
                ]);
            }
        }
        
        // === ЗАПИСИ ВРЕМЕНИ ===
        $db->exec("DELETE FROM time_records");
        if (!empty($data['timeRecords'])) {
            $stmt = $db->prepare("
                INSERT INTO time_records (id, date, employee_id, check_in, check_out) 
                VALUES (:id, :date, :employee_id, :check_in, :check_out)
            ");
            foreach ($data['timeRecords'] as $rec) {
                // Нормализация полей для совместимости
                $empId = $rec['employeeId'] ?? $rec['employee_id'] ?? null;
                $checkIn = $rec['checkIn'] ?? $rec['check_in'] ?? null;
                $checkOut = $rec['checkOut'] ?? $rec['check_out'] ?? null;
                
                $stmt->execute([
                    ':id' => $rec['id'] ?? null,
                    ':date' => $rec['date'],
                    ':employee_id' => $empId,
                    ':check_in' => $checkIn,
                    ':check_out' => $checkOut
                ]);
            }
        }
        
        // === ШТРАФЫ ===
        $db->exec("DELETE FROM fines");
        if (!empty($data['fines'])) {
            $stmt = $db->prepare("
                INSERT INTO fines (id, date, employee_id, reason, amount, comment, status) 
                VALUES (:id, :date, :employee_id, :reason, :amount, :comment, :status)
            ");
            foreach ($data['fines'] as $fine) {
                $empId = $fine['employeeId'] ?? $fine['employee_id'] ?? null;
                $stmt->execute([
                    ':id' => $fine['id'] ?? null,
                    ':date' => $fine['date'],
                    ':employee_id' => $empId,
                    ':reason' => $fine['reason'],
                    ':amount' => $fine['amount'],
                    ':comment' => $fine['comment'] ?? null,
                    ':status' => $fine['status'] ?? 'Активен'
                ]);
            }
        }
        
        // === ПРИЁМКИ ===
        $db->exec("DELETE FROM receivings");
        if (!empty($data['receivings'])) {
            $stmt = $db->prepare("
                INSERT INTO receivings (id, number, date, supplier, invoice, comment, product_id, qty, status) 
                VALUES (:id, :number, :date, :supplier, :invoice, :comment, :product_id, :qty, :status)
            ");
            foreach ($data['receivings'] as $rec) {
                $stmt->execute([
                    ':id' => $rec['id'],
                    ':number' => $rec['number'],
                    ':date' => $rec['date'],
                    ':supplier' => $rec['supplier'] ?? null,
                    ':invoice' => $rec['invoice'] ?? null,
                    ':comment' => $rec['comment'] ?? null,
                    ':product_id' => $rec['productId'] ?? $rec['product_id'] ?? null,
                    ':qty' => $rec['qty'] ?? 0,
                    ':status' => $rec['status'] ?? 'pending'
                ]);
            }
        }
        
        // === ОТГРУЗКИ ===
        $db->exec("DELETE FROM shippings");
        if (!empty($data['shippings'])) {
            $stmt = $db->prepare("
                INSERT INTO shippings (id, number, date, client, order_num, address, product_id, qty, status) 
                VALUES (:id, :number, :date, :client, :order_num, :address, :product_id, :qty, :status)
            ");
            foreach ($data['shippings'] as $ship) {
                $stmt->execute([
                    ':id' => $ship['id'],
                    ':number' => $ship['number'],
                    ':date' => $ship['date'],
                    ':client' => $ship['client'] ?? null,
                    ':order_num' => $ship['order'] ?? $ship['order_num'] ?? null,
                    ':address' => $ship['address'] ?? null,
                    ':product_id' => $ship['productId'] ?? $ship['product_id'] ?? null,
                    ':qty' => $ship['qty'] ?? 0,
                    ':status' => $ship['status'] ?? 'pending'
                ]);
            }
        }
        
        // === API КЛЮЧИ ===
        $db->exec("DELETE FROM api_keys");
        if (!empty($data['apiKeys'])) {
            $stmt = $db->prepare("
                INSERT INTO api_keys (id, client, key, status) 
                VALUES (:id, :client, :key, :status)
            ");
            foreach ($data['apiKeys'] as $key) {
                $stmt->execute([
                    ':id' => $key['id'],
                    ':client' => $key['client'],
                    ':key' => $key['key'],
                    ':status' => $key['status'] ?? 'active'
                ]);
            }
        }
        
        // === МЕТАДАННЫЕ (следующие ID) ===
        if (isset($data['nextEmpId'])) setMeta($db, 'nextEmpId', $data['nextEmpId']);
        if (isset($data['nextProdId'])) setMeta($db, 'nextProdId', $data['nextProdId']);
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

// === ОБРАБОТКА ЗАПРОСОВ ===

$action = $_GET['action'] ?? '';

// GET — получить данные
if ($action === 'get') {
    try {
        $data = getAllData($db);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get data: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// POST — сохранить данные
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        saveAllData($db, $data);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Данные успешно сохранены',
            'timestamp' => date('c')
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save data: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// === ОТВЕТ ПО УМОЛЧАНИЮ ===
http_response_code(400);
echo json_encode([
    'error' => 'Unknown action or method',
    'available_actions' => ['get', 'save'],
    'method' => $_SERVER['REQUEST_METHOD']
], JSON_UNESCAPED_UNICODE);
?>