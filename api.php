<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

// 🔽 НАСТРОЙКИ БД 🔽
$db_host = 'localhost';
$db_name = 'lineteplam';
$db_user = 'lineteplam'; // Заменить!
$db_pass = 'Aleks-1987';      // Заменить!
// 🔼 КОНЕЦ НАСТРОЕК 🔼

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB: ' . $e->getMessage()]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$table  = $input['table']  ?? '';
$data   = $input['data']   ?? null;
$id     = $input['id']     ?? ($data['id'] ?? '');

if (!$table && $action !== 'login') { echo json_encode(['error' => 'Table required']); exit; }

switch ($action) {
    case 'get_all':
        $stmt = $pdo->query("SELECT * FROM `$table` ORDER BY id DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'get_by_id':
        $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        break;

    case 'save':
        if (!$data) { echo json_encode(['error' => 'Data required']); exit; }
        $id = $data['id'] ?? 'fp_' . uniqid();
        $data['id'] = $id;

        $check = $pdo->prepare("SELECT id FROM `$table` WHERE id = ?");
        $check->execute([$id]);
        if ($check->fetch()) {
            $fields = array_filter($data, fn($k) => $k !== 'id', ARRAY_FILTER_USE_KEY);
            $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($fields)));
            $stmt = $pdo->prepare("UPDATE `$table` SET $set WHERE id = ?");
            $stmt->execute([...array_values($fields), $id]);
        } else {
            $fields = array_keys($data);
            $cols = implode(', ', array_map(fn($k) => "`$k`", $fields));
            $ph = implode(', ', array_fill(0, count($fields), '?'));
            $stmt = $pdo->prepare("INSERT INTO `$table` ($cols) VALUES ($ph)");
            $stmt->execute(array_values($data));
        }
        echo json_encode(['success' => true, 'id' => $id]);
        break;

    case 'delete':
        $pdo->prepare("DELETE FROM `$table` WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        break;

    case 'login':
        $user = $input['username'] ?? '';
        $pass = $input['password'] ?? '';
        if ($user === 'admin' && $pass === 'admin123') {
            echo json_encode(['success' => true, 'user' => ['username'=>'admin','role'=>'admin','name'=>'Администратор']]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password_hash = ?");
            $stmt->execute([$user, hash('sha256', $pass)]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            echo $u ? json_encode(['success'=>true,'user'=>$u]) : json_encode(['success'=>false,'error'=>'Неверные данные']);
        }
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
?>