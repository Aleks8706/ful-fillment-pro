<?php
// Этот файл подключается из system.php
// $db, $isLogged, $_SESSION уже доступны

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$api = $_POST['api'] ?? '';

function H($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function R($ok, $data = []) {
    $r = ['ok' => $ok];
    if (!$ok && isset($data['error'])) $r['error'] = $data['error'];
    elseif (is_array($data)) $r = array_merge($r, $data);
    echo json_encode($r, JSON_UNESCAPED_UNICODE);
    exit;
}
function logIt($db, $uid, $uname, $action, $module) {
    try {
        $db->prepare("INSERT INTO logs (user_id,user_name,action,module) VALUES (?,?,?,?)")
           ->execute([$uid,$uname,$action,$module]);
    } catch(Exception $e) {}
}

// ============ УСТАНОВКА ============
if ($api === 'do_install') {
    try {
        $host = trim($_POST['host']);
        $name = trim($_POST['dbname']);
        $user = trim($_POST['dbuser']);
        $pass = $_POST['dbpass'];
        
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4");
        $pdo->exec("USE `$name`");
        
        // Таблицы
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) UNIQUE, password VARCHAR(255), name VARCHAR(255), role VARCHAR(50) DEFAULT 'worker', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (id INT AUTO_INCREMENT PRIMARY KEY, article VARCHAR(100), name VARCHAR(255), barcode VARCHAR(100), quantity INT DEFAULT 0, location VARCHAR(100), min_quantity INT DEFAULT 10, price DECIMAL(10,2) DEFAULT 0, expiry DATE NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS staff (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255), position VARCHAR(100), rate DECIMAL(10,2) DEFAULT 0, status VARCHAR(50) DEFAULT 'active', phone VARCHAR(50), email VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS shipments (id INT AUTO_INCREMENT PRIMARY KEY, platform VARCHAR(50), boxes INT DEFAULT 0, pallets INT DEFAULT 0, weight DECIMAL(10,2) DEFAULT 0, status VARCHAR(50) DEFAULT 'new', tracking_number VARCHAR(100), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS messages (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, user_name VARCHAR(255), text TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS kiz_codes (id INT AUTO_INCREMENT PRIMARY KEY, product_id INT, code VARCHAR(255), status VARCHAR(50) DEFAULT 'active', scanned_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, user_name VARCHAR(255), action VARCHAR(255), module VARCHAR(50), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS time_logs (id INT AUTO_INCREMENT PRIMARY KEY, staff_id INT, staff_name VARCHAR(255), date DATE, check_in DATETIME NULL, check_out DATETIME NULL, hours DECIMAL(5,2) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS receiving_plan (id INT AUTO_INCREMENT PRIMARY KEY, barcode VARCHAR(100), article VARCHAR(100), name VARCHAR(255), expected_qty INT DEFAULT 0, received_qty INT DEFAULT 0, status VARCHAR(50) DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_pallets (id INT AUTO_INCREMENT PRIMARY KEY, platform VARCHAR(50), weight DECIMAL(10,2) DEFAULT 0, height DECIMAL(10,2) DEFAULT 0, priority INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS tz_items (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, platform VARCHAR(50), article VARCHAR(100), barcode VARCHAR(100), name VARCHAR(500), quantity INT DEFAULT 0, boxes INT DEFAULT 0, pallet INT DEFAULT 0, price DECIMAL(10,2) DEFAULT 0, status VARCHAR(50) DEFAULT 'pending', label_printed INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS boxes (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, platform VARCHAR(50), box_number INT DEFAULT 0, pallet_number INT DEFAULT 0, weight DECIMAL(10,2) DEFAULT 0, items_count INT DEFAULT 0, status VARCHAR(50) DEFAULT 'open', barcode VARCHAR(100), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS box_items (id INT AUTO_INCREMENT PRIMARY KEY, box_id INT, article VARCHAR(100), barcode VARCHAR(100), name VARCHAR(500), quantity INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (id INT AUTO_INCREMENT PRIMARY KEY, `key` VARCHAR(100) UNIQUE, value TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        
        // Начальные настройки
        $defaults = [
            'company_name' => 'FulFillPro',
            'company_address' => '',
            'company_phone' => '',
            'work_hours_norm' => '160',
            'overtime_coef_1' => '1.5',
            'overtime_coef_2' => '2.0'
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (`key`, value) VALUES (?, ?)");
        foreach ($defaults as $k => $v) $stmt->execute([$k, $v]);
        
        // Пользователи
        $c = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($c == 0) {
            $s = $pdo->prepare("INSERT INTO users (email,password,name,role) VALUES (?,?,?,?)");
            $s->execute(['admin@fulfill.pro', password_hash('admin123', PASSWORD_DEFAULT), 'Администратор', 'admin']);
            $s->execute(['manager@fulfill.pro', password_hash('manager123', PASSWORD_DEFAULT), 'Менеджер', 'manager']);
            $s->execute(['worker@fulfill.pro', password_hash('worker123', PASSWORD_DEFAULT), 'Сотрудник', 'worker']);
        }
        
        $cfg = "<?php\n";
        $cfg .= "define('DB_HOST', " . var_export($host, true) . ");\n";
        $cfg .= "define('DB_NAME', " . var_export($name, true) . ");\n";
        $cfg .= "define('DB_USER', " . var_export($user, true) . ");\n";
        $cfg .= "define('DB_PASS', " . var_export($pass, true) . ");\n";
        file_put_contents(__DIR__ . '/config.php', $cfg);
        
        R(true);
    } catch (Exception $e) {
        R(false, ['error' => $e->getMessage()]);
    }
}

// ============ ЛОГИН ============
if ($api === 'login') {
    if (!$db) R(false, ['error' => 'БД не подключена']);
    $s = $db->prepare("SELECT * FROM users WHERE email=?");
    $s->execute([$_POST['email']]);
    $u = $s->fetch();
    if ($u && password_verify($_POST['password'], $u['password'])) {
        $_SESSION['uid'] = $u['id'];
        $_SESSION['uname'] = $u['name'];
        $_SESSION['urole'] = $u['role'];
        R(true);
    } else {
        R(false, ['error' => 'Неверный email или пароль']);
    }
}

if ($api === 'logout') { session_destroy(); R(true); }

if (!$db) R(false, ['error' => 'БД не подключена']);
if (!isset($_SESSION['uid'])) R(false, ['error' => 'Требуется вход']);

$uid = $_SESSION['uid'];
$uname = $_SESSION['uname'];

// ========================================================================
// ============ DASHBOARD ============
// ========================================================================
if ($api === 'dashboard') {
    $products = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $prodQty = (int)$db->query("SELECT COALESCE(SUM(quantity),0) FROM products")->fetchColumn();
    $lowStock = (int)$db->query("SELECT COUNT(*) FROM products WHERE quantity < min_quantity")->fetchColumn();
    $staff = (int)$db->query("SELECT COUNT(*) FROM staff WHERE status='active'")->fetchColumn();
    $shipToday = (int)$db->query("SELECT COUNT(*) FROM shipments WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $shipWeek = (int)$db->query("SELECT COUNT(*) FROM shipments WHERE YEARWEEK(created_at)=YEARWEEK(NOW())")->fetchColumn();
    $ozonShip = (int)$db->query("SELECT COUNT(*) FROM shipments WHERE platform='ozon'")->fetchColumn();
    $wbShip = (int)$db->query("SELECT COUNT(*) FROM shipments WHERE platform='wb'")->fetchColumn();
    $kizCount = (int)$db->query("SELECT COUNT(*) FROM kiz_codes")->fetchColumn();
    $kizScanned = (int)$db->query("SELECT COUNT(*) FROM kiz_codes WHERE scanned_at IS NOT NULL")->fetchColumn();
    $msgsToday = (int)$db->query("SELECT COUNT(*) FROM messages WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    
    $chartData = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $count = (int)$db->query("SELECT COUNT(*) FROM shipments WHERE DATE(created_at)='$date'")->fetchColumn();
        $chartData[] = ['date' => $date, 'count' => $count];
    }
    
    $topProducts = $db->query("SELECT * FROM products ORDER BY quantity DESC LIMIT 5")->fetchAll();
    $lowProducts = $db->query("SELECT * FROM products WHERE quantity < min_quantity ORDER BY quantity ASC LIMIT 5")->fetchAll();
    $logs = $db->query("SELECT * FROM logs ORDER BY id DESC LIMIT 10")->fetchAll();
    
    $hour = (int)date('H');
    $greeting = $hour < 6 ? 'Доброй ночи' : ($hour < 12 ? 'Доброе утро' : ($hour < 18 ? 'Добрый день' : 'Добрый вечер'));
    
    $h = '<h1 style="margin-bottom:20px">📊 Панель управления</h1>';
    $h .= '<div class="card card-gradient"><h2 style="color:white">'.$greeting.', '.H($uname).'! 👋</h2><p style="opacity:0.9;margin-top:8px">Сегодня '.date('d.m.Y').' • Роль: <strong>'.H($_SESSION['urole']).'</strong></p></div>';
    
    $h .= '<div class="stats">';
    $h .= '<div class="stat"><div class="stat-icon">📦</div><div class="stat-value">'.$products.'</div><div class="stat-label">Товаров ('.$prodQty.' ед.)</div></div>';
    $h .= '<div class="stat"><div class="stat-icon">📤</div><div class="stat-value">'.$shipToday.'</div><div class="stat-label">Отгрузок сегодня</div></div>';
    $h .= '<div class="stat"><div class="stat-icon">👥</div><div class="stat-value">'.$staff.'</div><div class="stat-label">Сотрудников</div></div>';
    $h .= '<div class="stat"><div class="stat-icon">⚠️</div><div class="stat-value">'.$lowStock.'</div><div class="stat-label">Заканчиваются</div></div>';
    $h .= '</div>';
    
    if ($lowStock > 0) {
        $h .= '<div class="card card-warning"><h3 style="color:#e17055">⚠️ Заканчиваются '.$lowStock.' товаров!</h3><button class="btn btn-warning" onclick="go(\'wms\')">Перейти на склад</button></div>';
    }
    
    $h .= '<div class="grid-2-1">';
    $h .= '<div class="card"><h3>📈 Отгрузки за 7 дней</h3><canvas id="dsh-chart" height="200"></canvas></div>';
    $h .= '<div class="card"><h3>⚡ Быстрые действия</h3>';
    $h .= '<button class="btn btn-primary btn-full" onclick="go(\'wms\')">🏭 Склад</button>';
    $h .= '<button class="btn btn-success btn-full" onclick="go(\'fbo\')">📤 Отгрузки</button>';
    $h .= '<button class="btn btn-warning btn-full" onclick="go(\'demo\')">📥 Приёмка</button>';
    $h .= '<button class="btn btn-secondary btn-full" onclick="go(\'chat\')">💬 Чат</button>';
    $h .= '</div></div>';
    
    $h .= '<div class="grid-4">';
    $h .= '<div class="card text-center"><h3 style="color:#005bff">🔵 Ozon</h3><p class="big-num" style="color:#005bff">'.$ozonShip.'</p><p class="sub-text">отгрузок</p></div>';
    $h .= '<div class="card text-center"><h3 style="color:#a020f0">🟣 WB</h3><p class="big-num" style="color:#a020f0">'.$wbShip.'</p><p class="sub-text">отгрузок</p></div>';
    $h .= '<div class="card text-center"><h3>🏷️ КИЗ</h3><p class="big-num">'.$kizCount.'</p><p class="sub-text">'.$kizScanned.' пробито</p></div>';
    $h .= '<div class="card text-center"><h3>💬 Чат</h3><p class="big-num" style="color:#00b894">'.$msgsToday.'</p><p class="sub-text">сегодня</p></div>';
    $h .= '</div>';
    
    $h .= '<div class="grid-2">';
    $h .= '<div class="card"><h3>🏆 Топ товаров</h3><table><thead><tr><th>Артикул</th><th>Название</th><th>Кол-во</th></tr></thead><tbody>';
    if (count($topProducts) == 0) $h .= '<tr><td colspan="3" class="empty">Нет товаров</td></tr>';
    foreach ($topProducts as $p) {
        $h .= '<tr><td><strong>'.H($p['article']).'</strong></td><td>'.H($p['name']).'</td><td><span class="badge badge-success">'.$p['quantity'].'</span></td></tr>';
    }
    $h .= '</tbody></table></div>';
    
    $h .= '<div class="card"><h3 style="color:#e17055">⚠️ Заканчиваются</h3><table><thead><tr><th>Артикул</th><th>Название</th><th>Остаток</th></tr></thead><tbody>';
    if (count($lowProducts) == 0) $h .= '<tr><td colspan="3" class="empty">Всё в порядке 👍</td></tr>';
    foreach ($lowProducts as $p) {
        $h .= '<tr><td><strong>'.H($p['article']).'</strong></td><td>'.H($p['name']).'</td><td><span class="badge badge-danger">'.$p['quantity'].' / '.$p['min_quantity'].'</span></td></tr>';
    }
    $h .= '</tbody></table></div></div>';
    
    $h .= '<div class="card"><h3>📋 Последние операции</h3><table><thead><tr><th>Время</th><th>Действие</th><th>Модуль</th><th>Пользователь</th></tr></thead><tbody>';
    if (count($logs) == 0) $h .= '<tr><td colspan="4" class="empty">Нет операций</td></tr>';
    foreach ($logs as $l) {
        $h .= '<tr><td>'.date('d.m H:i', strtotime($l['created_at'])).'</td><td>'.H($l['action']).'</td><td><span class="badge badge-info">'.H($l['module']).'</span></td><td>'.H($l['user_name']).'</td></tr>';
    }
    $h .= '</tbody></table></div>';
    
    $h .= '<script>drawDashboardChart('.json_encode($chartData).');</script>';
    
    R(true, ['html' => $h]);
}

// ========================================================================
// ============ WMS (СКЛАД) ============
// ========================================================================
if ($api === 'wms') {
    $rows = $db->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();
    $total = array_sum(array_column($rows,'quantity'));
    $low = 0; $value = 0;
    foreach ($rows as $r) { 
        if ($r['quantity'] < $r['min_quantity']) $low++;
        $value += $r['quantity'] * $r['price'];
    }
    
    $h = '<h1>🏭 Склад (WMS)</h1>';
    $h .= '<div class="stats">';
    $h .= '<div class="stat"><div class="stat-value">'.count($rows).'</div><div class="stat-label">Позиций</div></div>';
    $h .= '<div class="stat"><div class="stat-value">'.$total.'</div><div class="stat-label">Единиц</div></div>';
    $h .= '<div class="stat"><div class="stat-value">'.$low.'</div><div class="stat-label">Заканчиваются</div></div>';
    $h .= '<div class="stat"><div class="stat-value">'.number_format($value,0,',',' ').' ₽</div><div class="stat-label">Стоимость</div></div>';
    $h .= '</div>';
    
    $h .= '<div class="card"><h3>📦 Товары ';
    $h .= '<button class="btn btn-success" onclick="addProduct()">➕ Добавить</button>';
    $h .= '<button class="btn btn-secondary" onclick="exportWMS()">📥 CSV</button>';
    $h .= '<button class="btn btn-secondary" onclick="importWMS()">📤 Импорт</button>';
    $h .= '</h3>';
    $h .= '<input type="text" class="search" placeholder="🔍 Поиск по названию, артикулу, ШК..." oninput="filterTable(\'wms-tbl\',this.value)">';
    $h .= '<div class="table-scroll"><table id="wms-tbl"><thead><tr><th>Артикул</th><th>Название</th><th>ШК</th><th>Кол-во</th><th>Мин.</th><th>Место</th><th>Цена</th><th>Срок</th><th></th></tr></thead><tbody>';
    if (count($rows) == 0) $h .= '<tr><td colspan="9" class="empty">Нет товаров. Добавьте первый.</td></tr>';
    foreach ($rows as $r) {
        $b = $r['quantity'] < $r['min_quantity'] ? 'badge-danger' : 'badge-success';
        $h .= '<tr>';
        $h .= '<td><strong>'.H($r['article']).'</strong></td>';
        $h .= '<td>'.H($r['name']).'</td>';
        $h .= '<td>'.H($r['barcode']).'</td>';
        $h .= '<td><span class="badge '.$b.'">'.$r['quantity'].'</span></td>';
        $h .= '<td>'.$r['min_quantity'].'</td>';
        $h .= '<td>'.H($r['location']).'</td>';
        $h .= '<td>'.number_format($r['price'],2).'</td>';
        $h .= '<td>'.H($r['expiry']).'</td>';
        $h .= '<td><button class="btn btn-secondary" onclick="editProduct('.$r['id'].')">✏️</button> <button class="btn btn-danger" onclick="delProduct('.$r['id'].')">🗑</button></td>';
        $h .= '</tr>';
    }
    $h .= '</tbody></table></div></div>';
    
    $h .= '<div class="card"><h3>📡 Сканирование (ТСД)</h3>';
    $h .= '<input type="text" id="wms-scan" class="scan-input" placeholder="Штрих-код..." onkeypress="if(event.key===\'Enter\')scanWMS()">';
    $h .= '<div id="scan-result" style="margin-top:15px"></div></div>';
    
    $h .= '<div class="card"><h3>📋 Инвентаризация</h3>';
    $h .= '<button class="btn btn-primary" onclick="toast(\'📝 Начата инвентаризация\',\'success\')">📝 Начать</button>';
    $h .= '<button class="btn btn-secondary" onclick="toast(\'📊 Отчёт формируется\',\'info\')">📊 Отчёт</button></div>';
    
    R(true, ['html' => $h]);
}

if ($api === 'add_product') {
    $db->prepare("INSERT INTO products (article,name,barcode,quantity,location,min_quantity,price,expiry) VALUES (?,?,?,?,?,?,?,?)")->execute([
        $_POST['article']??'', $_POST['name']??'', $_POST['barcode']??'',
        (int)($_POST['quantity']??0), $_POST['location']??'',
        (int)($_POST['min_quantity']??10), (float)($_POST['price']??0),
        $_POST['expiry']??null
    ]);
    logIt($db,$uid,$uname,'Добавлен товар: '.($_POST['name']??''),'wms');
    R(true);
}
if ($api === 'edit_product') {
    $db->prepare("UPDATE products SET article=?,name=?,barcode=?,quantity=?,location=?,min_quantity=?,price=?,expiry=? WHERE id=?")->execute([
        $_POST['article']??'', $_POST['name']??'', $_POST['barcode']??'',
        (int)($_POST['quantity']??0), $_POST['location']??'',
        (int)($_POST['min_quantity']??10), (float)($_POST['price']??0),
        $_POST['expiry']??null, (int)$_POST['id']
    ]);
    R(true);
}
if ($api === 'del_product') { 
    $db->prepare("DELETE FROM products WHERE id=?")->execute([(int)$_POST['id']]); 
    R(true); 
}
if ($api === 'get_product') {
    $s = $db->prepare("SELECT * FROM products WHERE id=?");
    $s->execute([(int)$_POST['id']]);
    R(true, ['product' => $s->fetch()]);
}
if ($api === 'scan_wms') {
    $s = $db->prepare("SELECT * FROM products WHERE barcode=?");
    $s->execute([$_POST['barcode']]);
    $p = $s->fetch();
    if ($p) R(true, ['found' => true, 'product' => $p]);
    else R(true, ['found' => false]);
}
if ($api === 'export_wms') {
    $rows = $db->query("SELECT * FROM products ORDER BY id")->fetchAll();
    R(true, ['data' => $rows]);
}

// ========================================================================
// ============ FBO ============
// ========================================================================
if ($api === 'fbo') {
    $rows = $db->query("SELECT * FROM shipments ORDER BY id DESC")->fetchAll();
    $totalBoxes = array_sum(array_column($rows,'boxes'));
    $totalPallets = array_sum(array_column($rows,'pallets'));
    
    $h = '<h1>📤 FBO Отгрузки</h1>';
    $h .= '<div class="stats">';
    $h .= '<div class="stat"><div class="stat-value">'.count($rows).'</div><div class="stat-label">Всего</div></div>';
    $h .= '<div class="stat"><div class="stat-value">'.$totalBoxes.'</div><div class="stat-label">Коробов</div></div>';
    $h .= '<div class="stat"><div class="stat-value">'.$totalPallets.'</div><div class="stat-label">Палет</div></div>';
    $h .= '</div>';
    
    $h .= '<div class="card"><h3>⚙️ Фильтр ';
    $h .= '<button class="btn btn-primary" onclick="filterShip(\'all\')">Все</button>';
    $h .= '<button class="btn btn-secondary" onclick="filterShip(\'wb\')">🟣 WB</button>';
    $h .= '<button class="btn btn-secondary" onclick="filterShip(\'ozon\')">🔵 Ozon</button>';
    $h .= '</h3></div>';
    
    $h .= '<div class="card"><h3>📋 Отгрузки <button class="btn btn-success" onclick="addShipment()">➕ Создать</button></h3>';
    $h .= '<div class="table-scroll"><table id="ship-tbl"><thead><tr><th>№</th><th>Платформа</th><th>Коробов</th><th>Палет</th><th>Вес</th><th>Статус</th><th>Трекинг</th><th>Дата</th><th></th></tr></thead><tbody>';
    if (count($rows) == 0) $h .= '<tr><td colspan="9" class="empty">Нет отгрузок</td></tr>';
    foreach ($rows as $i=>$r) {
        $plMap = ['wb' => '🟣 WB', 'ozon' => '🔵 Ozon'];
        $pl = isset($plMap[$r['platform']]) ? $plMap[$r['platform']] : H($r['platform']);
        $stMap = ['new'=>'📝 Новая','processing'=>'🚚 В пути','done'=>'✅ Отгружена','cancelled'=>'❌ Отмена'];
        $st = isset($stMap[$r['status']]) ? $stMap[$r['status']] : H($r['status']);
        $h .= '<tr data-platform="'.H($r['platform']).'">';
        $h .= '<td>'.($i+1).'</td>';
        $h .= '<td>'.$pl.'</td>';
        $h .= '<td>'.$r['boxes'].'</td>';
        $h .= '<td>'.$r['pallets'].'</td>';
        $h .= '<td>'.$r['weight'].' кг</td>';
        $h .= '<td><span class="badge badge-info">'.$st.'</span></td>';
        $h .= '<td>'.H($r['tracking_number']??'-').'</td>';
        $h .= '<td>'.date('d.m.Y H:i',strtotime($r['created_at'])).'</td>';
        $h .= '<td><button class="btn btn-danger" onclick="delShipment('.$r['id'].')">🗑</button></td>';
        $h .= '</tr>';
    }
    $h .= '</tbody></table></div></div>';
    
    R(true, ['html' => $h]);
}
if ($api === 'add_shipment') {
    $db->prepare("INSERT INTO shipments (platform,boxes,pallets,weight,status,tracking_number) VALUES (?,?,?,?,?,?)")->execute([
        $_POST['platform'], (int)$_POST['boxes'], (int)$_POST['pallets'],
        (float)$_POST['weight'], $_POST['status']??'new', $_POST['tracking']??''
    ]);
    logIt($db,$uid,$uname,'Отгрузка '.$_POST['platform'],'fbo');
    R(true);
}
if ($api === 'del_shipment') { 
    $db->prepare("DELETE FROM shipments WHERE id=?")->execute([(int)$_POST['id']]); 
    R(true); 
}

// ========================================================================
// ============ OZON / WB ============
// ========================================================================
function renderMarketplaceModule($db, $platform, $title, $color) {
    global $uid, $uname;
    $tz = $db->query("SELECT * FROM tz_items WHERE platform='$platform' AND user_id=$uid ORDER BY id DESC")->fetchAll();
    $boxes = $db->query("SELECT * FROM boxes WHERE platform='$platform' AND user_id=$uid ORDER BY id DESC")->fetchAll();
    $totalPositions = count($tz);
    $totalQty = array_sum(array_column($tz,'quantity'));
    $printedLabels = 0;
    foreach($tz as $t) if($t['label_printed'] > 0) $printedLabels++;
    
    $h = "<h1>$title — Управление отгрузками</h1>";
    $h .= '<div class="stats">';
    $h .= '<div class="stat"><div class="stat-icon">📋</div><div class="stat-value">'.$totalPositions.'</div><div class="stat-label">Позиций в ТЗ</div></div>';
    $h .= '<div class="stat"><div class="stat-icon">📦</div><div class="stat-value">'.$totalQty.'</div><div class="stat-label">Единиц всего</div></div>';
    $h .= '<div class="stat"><div class="stat-icon">🖨️</div><div class="stat-value">'.$printedLabels.'</div><div class="stat-label">Распечатано</div></div>';
    $h .= '<div class="stat"><div class="stat-icon">📦</div><div class="stat-value">'.count($boxes).'</div><div class="stat-label">Коробов</div></div>';
    $h .= '</div>';
    
    $h .= '<div class="card"><h3>📥 Загрузка ТЗ (CSV) <button class="btn btn-secondary" onclick="clearTZ(\''.$platform.'\')">🗑 Очистить</button></h3>';
    $h .= '<p class="sub-text">Формат CSV (разделитель ;): артикул;штрих-код;название;количество;коробов;палет;цена</p>';
    $h .= '<textarea id="'.$platform.'-csv" class="search" rows="6" placeholder="ART-001;4600000001;Товар 1;100;10;1;299.90&#10;ART-002;4600000002;Товар 2;50;5;1;599.00"></textarea>';
    $h .= '<button class="btn btn-success" onclick="importCSV(\''.$platform.'\')">📥 Загрузить</button>';
    $h .= '<button class="btn btn-secondary" onclick="downloadCSVTemplate()">📊 Шаблон</button></div>';
    
    $h .= '<div class="card"><h3>🏷️ Этикетки</h3>';
    $h .= '<button class="btn btn-primary" onclick="printLabels(\''.$platform.'\',\'all\')">🖨️ Печать всех</button>';
    $h .= '<button class="btn btn-secondary" onclick="printLabels(\''.$platform.'\',\'new\')">🖨️ Только новые</button>';
    $h .= '<button class="btn btn-warning" onclick="resetLabels(\''.$platform.'\')">🔄 Сброс</button></div>';
    
    $h .= '<div class="card"><h3>📋 ТЗ <input type="text" class="search" placeholder="🔍 Поиск..." oninput="filterTable(\''.$platform.'-tz\',this.value)" style="max-width:300px;margin:0"></h3>';
    $h .= '<div class="table-scroll"><table id="'.$platform.'-tz"><thead><tr><th>№</th><th>Арт.</th><th>ШК</th><th>Наименование</th><th>Кол-во</th><th>Коробов</th><th>Палет</th><th>Цена</th><th>Статус</th><th></th></tr></thead><tbody>';
    if ($totalPositions == 0) $h .= '<tr><td colspan="10" class="empty">ТЗ не загружено</td></tr>';
    foreach ($tz as $i=>$t) {
        $stBadge = $t['status']==='done' ? 'badge-success' : ($t['status']==='processing' ? 'badge-info' : 'badge-warning');
        $stMap = ['pending'=>'⏳ Ожидает','processing'=>'🔄 В работе','done'=>'✅ Собрано'];
        $stText = isset($stMap[$t['status']]) ? $stMap[$t['status']] : H($t['status']);
        $h .= '<tr>';
        $h .= '<td>'.($i+1).'</td>';
        $h .= '<td><strong>'.H($t['article']).'</strong></td>';
        $h .= '<td><code>'.H($t['barcode']).'</code></td>';
        $h .= '<td>'.H($t['name']).'</td>';
        $h .= '<td><span class="badge badge-info">'.$t['quantity'].'</span></td>';
        $h .= '<td>'.$t['boxes'].'</td>';
        $h .= '<td>'.$t['pallet'].'</td>';
        $h .= '<td>'.number_format($t['price'],2).' ₽</td>';
        $h .= '<td><span class="badge '.$stBadge.'">'.$stText.'</span></td>';
        $h .= '<td><button class="btn btn-primary" onclick="printOneLabel(\''.$platform.'\','.$t['id'].')">🏷️</button> <button class="btn btn-danger" onclick="delTZ('.$t['id'].')">🗑</button></td>';
        $h .= '</tr>';
    }
    $h .= '</tbody></table></div></div>';
    
    $h .= '<div class="card"><h3>📦 Короба <button class="btn btn-success" onclick="createBox(\''.$platform.'\')">➕ Новый</button></h3>';
    $h .= '<div class="table-scroll"><table><thead><tr><th>№</th><th>Палет</th><th>Вес</th><th>Позиций</th><th>ШК</th><th>Статус</th><th></th></tr></thead><tbody>';
    if (count($boxes) == 0) $h .= '<tr><td colspan="7" class="empty">Нет коробов</td></tr>';
    foreach ($boxes as $b) {
        $stBadge = $b['status']==='closed' ? 'badge-success' : 'badge-warning';
        $stText = $b['status']==='closed' ? '✅ Закрыт' : '📂 Открыт';
        $h .= '<tr>';
        $h .= '<td><strong>#'.$b['box_number'].'</strong></td>';
        $h .= '<td>'.$b['pallet_number'].'</td>';
        $h .= '<td>'.$b['weight'].' кг</td>';
        $h .= '<td>'.$b['items_count'].'</td>';
        $h .= '<td><code>'.H($b['barcode']??'-').'</code></td>';
        $h .= '<td><span class="badge '.$stBadge.'">'.$stText.'</span></td>';
        $h .= '<td>';
        $h .= '<button class="btn btn-primary" onclick="scanToBox('.$b['id'].')">📡</button>';
        $h .= '<button class="btn btn-success" onclick="closeBox('.$b['id'].')">✅</button>';
        $h .= '<button class="btn btn-danger" onclick="delBox('.$b['id'].')">🗑</button>';
        $h .= '</td></tr>';
    }
    $h .= '</tbody></table></div></div>';
    
    $h .= '<div class="card"><h3>📡 Сканирование</h3>';
    $h .= '<input type="text" id="'.$platform.'-scan" class="scan-input" placeholder="Штрих-код..." onkeypress="if(event.key===\'Enter\')marketplaceScan(\''.$platform.'\')"></div>';
    
    $h .= '<div class="card"><h3>📊 Отчёт <button class="btn btn-secondary" onclick="exportReport(\''.$platform.'\')">📥 CSV</button></h3></div>';
    
    return $h;
}

if ($api === 'ozon') {
    R(true, ['html' => renderMarketplaceModule($db, 'ozon', '🔵 Ozon', '#005bff')]);
}
if ($api === 'wb') {
    R(true, ['html' => renderMarketplaceModule($db, 'wb', '🟣 Wildberries', '#a020f0')]);
}

if ($api === 'import_csv') {
    $platform = $_POST['platform'];
    $csv = $_POST['csv'] ?? '';
    $lines = array_filter(explode("\n", str_replace("\r", '', $csv)));
    $count = 0;
    $db->prepare("DELETE FROM tz_items WHERE platform=? AND user_id=?")->execute([$platform, $uid]);
    foreach ($lines as $line) {
        $line = trim($line);
        if (!$line) continue;
        $parts = explode(';', $line);
        if (count($parts) < 4) continue;
        $db->prepare("INSERT INTO tz_items (user_id,platform,article,barcode,name,quantity,boxes,pallet,price) VALUES (?,?,?,?,?,?,?,?,?)")->execute([
            $uid, $platform,
            $parts[0] ?? '',
            $parts[1] ?? '',
            $parts[2] ?? '',
            (int)($parts[3] ?? 0),
            (int)($parts[4] ?? 0),
            (int)($parts[5] ?? 0),
            (float)($parts[6] ?? 0)
        ]);
        $count++;
    }
    logIt($db,$uid,$uname,'Загружено ТЗ '.$platform.': '.$count,$platform);
    R(true, ['count' => $count]);
}
if ($api === 'clear_tz') {
    $db->prepare("DELETE FROM tz_items WHERE platform=? AND user_id=?")->execute([$_POST['platform'], $uid]);
    $db->prepare("DELETE FROM box_items WHERE box_id IN (SELECT id FROM boxes WHERE platform=? AND user_id=?)")->execute([$_POST['platform'], $uid]);
    $db->prepare("DELETE FROM boxes WHERE platform=? AND user_id=?")->execute([$_POST['platform'], $uid]);
    R(true);
}
if ($api === 'del_tz') { 
    $db->prepare("DELETE FROM tz_items WHERE id=? AND user_id=?")->execute([(int)$_POST['id'], $uid]); 
    R(true); 
}
if ($api === 'get_labels') {
    $platform = $_POST['platform'];
    $type = $_POST['type'];
    $where = $type==='new' ? ' AND label_printed=0' : '';
    $tz = $db->query("SELECT * FROM tz_items WHERE user_id=$uid AND platform='$platform'$where")->fetchAll();
    $labels = [];
    foreach ($tz as $t) {
        for ($i=0; $i < $t['quantity']; $i++) {
            $labels[] = [
                'id' => $t['id'],
                'article' => $t['article'],
                'barcode' => $t['barcode'],
                'name' => $t['name'],
                'price' => (float)$t['price'],
                'number' => $i+1,
                'total' => (int)$t['quantity'],
                'platform' => $platform
            ];
        }
        $db->prepare("UPDATE tz_items SET label_printed=label_printed+1 WHERE id=?")->execute([$t['id']]);
    }
    R(true, ['labels' => $labels]);
}
if ($api === 'reset_labels') { 
    $db->prepare("UPDATE tz_items SET label_printed=0 WHERE platform=? AND user_id=?")->execute([$_POST['platform'], $uid]); 
    R(true); 
}
if ($api === 'create_box') {
    $platform = $_POST['platform'];
    $max = (int)$db->query("SELECT COALESCE(MAX(box_number),0) FROM boxes WHERE user_id=$uid AND platform='$platform'")->fetchColumn();
    $newNum = $max + 1;
    $pallet = (int)($_POST['pallet'] ?? 1);
    $db->prepare("INSERT INTO boxes (user_id,platform,box_number,pallet_number,status) VALUES (?,?,?,?,?)")->execute([$uid, $platform, $newNum, $pallet, 'open']);
    logIt($db,$uid,$uname,'Создан короб #'.$newNum.' '.$platform,$platform);
    R(true, ['box_number' => $newNum]);
}
if ($api === 'scan_to_box') {
    $boxId = (int)$_POST['box_id'];
    $barcode = trim($_POST['barcode']);
    $b = $db->prepare("SELECT * FROM boxes WHERE id=? AND user_id=?");
    $b->execute([$boxId, $uid]);
    $box = $b->fetch();
    if (!$box || $box['status']==='closed') R(false, ['error' => 'Короб закрыт или не существует']);
    $s = $db->prepare("SELECT * FROM tz_items WHERE user_id=? AND (barcode=? OR article=?)");
    $s->execute([$uid, $barcode, $barcode]);
    $tz = $s->fetch();
    if (!$tz) R(false, ['error' => 'Не найдено в ТЗ: '.$barcode]);
    $db->prepare("INSERT INTO box_items (box_id,article,barcode,name,quantity) VALUES (?,?,?,?,1)")->execute([$boxId, $tz['article'], $tz['barcode'], $tz['name']]);
    $db->prepare("UPDATE boxes SET items_count=items_count+1 WHERE id=?")->execute([$boxId]);
    logIt($db,$uid,$uname,'Скан '.$tz['article'].' в короб #'.$box['box_number'],$box['platform']);
    R(true, ['product' => ['article' => $tz['article'], 'name' => $tz['name'], 'box' => $box['box_number']]]);
}
if ($api === 'close_box') {
    $b = $db->prepare("SELECT * FROM boxes WHERE id=? AND user_id=?");
    $b->execute([(int)$_POST['id'], $uid]);
    $box = $b->fetch();
    if (!$box) R(false, ['error' => 'Не найден']);
    $bc = strtoupper($box['platform']).'-BOX-'.$box['box_number'].'-'.date('Ymd').'-'.substr(md5($box['id'].time()),0,6);
    $w = $box['items_count'] * 0.5;
    $db->prepare("UPDATE boxes SET status='closed',barcode=?,weight=? WHERE id=?")->execute([$bc, $w, $box['id']]);
    logIt($db,$uid,$uname,'Закрыт короб #'.$box['box_number'],$box['platform']);
    R(true, ['barcode' => $bc]);
}
if ($api === 'del_box') {
    $db->prepare("DELETE FROM box_items WHERE box_id=?")->execute([(int)$_POST['id']]);
    $db->prepare("DELETE FROM boxes WHERE id=? AND user_id=?")->execute([(int)$_POST['id'], $uid]);
    R(true);
}
if ($api === 'export_report') {
    $platform = $_POST['platform'];
    $tz = $db->query("SELECT * FROM tz_items WHERE user_id=$uid AND platform='$platform'")->fetchAll();
    $boxes = $db->query("SELECT * FROM boxes WHERE user_id=$uid AND platform='$platform' ORDER BY pallet_number,box_number")->fetchAll();
    foreach ($boxes as &$b) {
        $its = $db->prepare("SELECT * FROM box_items WHERE box_id=?");
        $its->execute([$b['id']]);
        $b['items'] = $its->fetchAll();
    }
    R(true, ['tz' => $tz, 'boxes' => $boxes]);
}

// ========================================================================
// ============ DELIVERY ============
// ========================================================================
if ($api === 'delivery') {
    $pallets = $db->query("SELECT * FROM delivery_pallets ORDER BY priority DESC, id")->fetchAll();
    $totalWeight = array_sum(array_column($pallets,'weight'));
    $maxWeight = 20000;
    $usagePercent = $maxWeight > 0 ? round($totalWeight/$maxWeight*100,1) : 0;
    
    $h = '<h1>🚛 Логистика — PalletLoad Pro</h1>';
    $h .= '<div class="stats">';
    $h .= '<div class="stat"><div class="stat-icon">🚛</div><div class="stat-value">13.6м</div><div class="stat-label">Длина кузова</div></div>';
    $h .= '<div class="stat"><div class="stat-icon">⚖️</div><div class="stat-value">'.number_format($totalWeight,0).' кг</div><div class="stat-label">Загружено ('.$usagePercent.'%)</div></div>';
    $h .= '<div class="stat"><div class="stat-icon">📦</div><div class="stat-value">'.count($pallets).'</div><div class="stat-label">Палет</div></div>';
    $h .= '<div class="stat"><div class="stat-icon">🎯</div><div class="stat-value">'.($usagePercent < 100 ? 'OK' : 'ПЕРЕГРУЗ').'</div><div class="stat-label">Баланс</div></div>';
    $h .= '</div>';
    
    $h .= '<div class="card"><h3>🗺️ 2D Схема кузова ';
    $h .= '<button class="btn btn-success" onclick="addPallet(\'ozon\')">+ Ozon</button>';
    $h .= '<button class="btn btn-secondary" onclick="addPallet(\'wb\')">+ WB</button>';
    $h .= '<button class="btn btn-warning" onclick="autoBalance()">⚖️ Авто-баланс</button>';
    $h .= '<button class="btn btn-danger" onclick="clearPallets()">🗑 Очистить</button>';
    $h .= '</h3>';
    $h .= '<canvas id="truck-canvas" width="800" height="300" style="width:100%;border:2px solid #ddd;border-radius:8px;background:#f8f9fa"></canvas>';
    $h .= '<p class="sub-text">🖱️ Клик по палете = удалить | Авто-расстановка по приоритету</p></div>';
    
    $h .= '<div class="card"><h3>📋 Список палет</h3>';
    $h .= '<table><thead><tr><th>#</th><th>Маркетплейс</th><th>Вес</th><th>Высота</th><th>Приоритет</th><th></th></tr></thead><tbody>';
    if (count($pallets) == 0) $h .= '<tr><td colspan="6" class="empty">Добавьте палеты</td></tr>';
    foreach ($pallets as $i=>$p) {
        $pl = $p['platform']==='wb' ? '🟣 WB' : '🔵 Ozon';
        $h .= '<tr><td>'.($i+1).'</td><td>'.$pl.'</td><td>'.$p['weight'].' кг</td><td>'.$p['height'].' см</td><td>'.$p['priority'].'</td><td><button class="btn btn-danger" onclick="delPallet('.$p['id'].')">🗑</button></td></tr>';
    }
    $h .= '</tbody></table></div>';
    
    $h .= '<div class="card"><h3>⚙️ Параметры кузова</h3>';
    $h .= '<div class="grid-3">';
    $h .= '<div><label>Длина (мм)</label><input type="number" class="search" value="13600" id="truck-len"></div>';
    $h .= '<div><label>Ширина (мм)</label><input type="number" class="search" value="2450" id="truck-w"></div>';
    $h .= '<div><label>Высота (мм)</label><input type="number" class="search" value="2700" id="truck-h"></div>';
    $h .= '</div></div>';
    
    $h .= '<script>drawTruck('.json_encode($pallets).');</script>';
    
    R(true, ['html' => $h]);
}
if ($api === 'add_pallet') {
    $platform = $_POST['platform'];
    $weight = (float)($_POST['weight'] ?? rand(300,800));
    $height = (float)($_POST['height'] ?? rand(100,200));
    $priority = $platform==='ozon' ? 2 : 1;
    $db->prepare("INSERT INTO delivery_pallets (platform,weight,height,priority) VALUES (?,?,?,?)")->execute([$platform, $weight, $height, $priority]);
    logIt($db,$uid,$uname,'Добавлен палет '.$platform,'delivery');
    R(true);
}
if ($api === 'del_pallet') { 
    $db->prepare("DELETE FROM delivery_pallets WHERE id=?")->execute([(int)$_POST['id']]); 
    R(true); 
}
if ($api === 'clear_pallets') { 
    $db->exec("DELETE FROM delivery_pallets"); 
    R(true); 
}
if ($api === 'auto_balance') {
    $db->exec("UPDATE delivery_pallets SET priority = CASE WHEN platform='ozon' THEN 2 ELSE 1 END");
    R(true);
}

// ========================================================================
// ============ KIZ ============
// ========================================================================
if ($api === 'kiz') {
    $codes = $db->query("SELECT k.*,p.name as pn,p.article as pa FROM kiz_codes k LEFT JOIN products p ON k.product_id=p.id ORDER BY k.id DESC LIMIT 200")->fetchAll();
    $prods = $db->query("SELECT id,name,article FROM products ORDER BY name")->fetchAll();
    $opts = '';
    foreach ($prods as $p) $opts .= '<option value="'.$p['id'].'">'.H($p['name']).' ('.H($p['article']).')</option>';
    $active = 0; $scanned = 0;
    foreach ($codes as $c) { if ($c['status']==='active') $active++; if ($c['scanned_at']!==null) $scanned++; }
    
    $h = '<h1>🏷️ КИЗ / Честный знак</h1>';
    $h .= '<div class="stats">';
    $h .= '<div class="stat"><div class="stat-value">'.count($codes).'</div><div class="stat-label">Всего кодов</div></div>';
    $h .= '<div class="stat"><div class="stat-value">'.$active.'</div><div class="stat-label">Активных</div></div>';
    $h .= '<div class="stat"><div class="stat-value">'.$scanned.'</div><div class="stat-label">Пробито</div></div>';
    $h .= '</div>';
    
    $h .= '<div class="card"><h3>📊 Импорт из CSV</h3>';
    $h .= '<p class="sub-text">Формат: код;ID_товара (или артикул)</p>';
    $h .= '<textarea id="kiz-csv" class="search" rows="5" placeholder="0104601234567890;ART-001&#10;0104601234567891;ART-002"></textarea>';
    $h .= '<button class="btn btn-success" onclick="importKIZ()">✅ Загрузить</button></div>';
    
    $h .= '<div class="card"><h3>🔲 Ручная привязка</h3>';
    $h .= '<label>Товар:</label><select class="search" id="kiz-prod"><option value="">-- Выберите --</option>'.$opts.'</select>';
    $h .= '<label>Коды (по одному на строку):</label><textarea class="search" id="kiz-codes" rows="4" placeholder="Коды КИЗ..."></textarea>';
    $h .= '<button class="btn btn-success" onclick="saveKIZ()">💾 Привязать</button></div>';
    
    $h .= '<div class="card"><h3>📋 Коды <input type="text" class="search" placeholder="🔍 Поиск..." oninput="filterTable(\'kiz-tbl\',this.value)" style="max-width:300px;margin:0"></h3>';
    $h .= '<div class="table-scroll"><table id="kiz-tbl"><thead><tr><th>Код</th><th>Товар</th><th>Арт.</th><th>Статус</th><th>Пробит</th><th></th></tr></thead><tbody>';
    if (count($codes) == 0) $h .= '<tr><td colspan="6" class="empty">Нет кодов</td></tr>';
    foreach ($codes as $c) {
        $st = $c['status']==='active' ? 'badge-success' : 'badge-danger';
        $h .= '<tr>';
        $h .= '<td><code style="font-size:11px">'.H($c['code']).'</code></td>';
        $h .= '<td>'.H($c['pn']??'—').'</td>';
        $h .= '<td>'.H($c['pa']??'-').'</td>';
        $h .= '<td><span class="badge '.$st.'">'.H($c['status']).'</span></td>';
        $h .= '<td>'.($c['scanned_at'] ? date('d.m H:i',strtotime($c['scanned_at'])) : 'Нет').'</td>';
        $h .= '<td><button class="btn btn-danger" onclick="delKIZ('.$c['id'].')">🗑</button></td>';
        $h .= '</tr>';
    }
    $h .= '</tbody></table></div></div>';
    
    $h .= '<div class="card"><h3>📡 Сканирование КИЗ</h3>';
    $h .= '<input type="text" id="kiz-scan" class="scan-input" placeholder="Сканируйте код..." onkeypress="if(event.key===\'Enter\')scanKIZ()"></div>';
    
    R(true, ['html' => $h]);
}
if ($api === 'save_kiz') {
    $pid = (int)$_POST['product_id'];
    $cs = array_filter(array_map('trim', explode("\n", $_POST['codes'])));
    $count = 0;
    foreach ($cs as $c) { 
        try { 
            $db->prepare("INSERT INTO kiz_codes (product_id,code) VALUES (?,?)")->execute([$pid,$c]); 
            $count++; 
        } catch(Exception $e) {} 
    }
    logIt($db,$uid,$uname,'Привязано '.$count.' КИЗ','kiz');
    R(true, ['count' => $count]);
}
if ($api === 'import_kiz') {
    $csv = $_POST['csv'] ?? '';
    $lines = array_filter(explode("\n", str_replace("\r",'',$csv)));
    $count = 0;
    foreach ($lines as $line) {
        $line = trim($line);
        if (!$line) continue;
        $parts = explode(';', $line);
        if (count($parts) < 2) continue;
        $code = trim($parts[0]);
        $artOrId = trim($parts[1]);
        if (is_numeric($artOrId)) {
            $s = $db->prepare("SELECT id FROM products WHERE id=?");
            $s->execute([(int)$artOrId]);
        } else {
            $s = $db->prepare("SELECT id FROM products WHERE article=?");
            $s->execute([$artOrId]);
        }
        $p = $s->fetch();
        if ($p) {
            try { 
                $db->prepare("INSERT INTO kiz_codes (product_id,code) VALUES (?,?)")->execute([$p['id'],$code]); 
                $count++; 
            } catch(Exception $e) {}
        }
    }
    logIt($db,$uid,$uname,'Импорт КИЗ: '.$count,'kiz');
    R(true, ['count' => $count]);
}
if ($api === 'del_kiz') { 
    $db->prepare("DELETE FROM kiz_codes WHERE id=?")->execute([(int)$_POST['id']]); 
    R(true); 
}
if ($api === 'scan_kiz') {
    $code = trim($_POST['code']);
    $s = $db->prepare("SELECT k.*,p.name,p.article FROM kiz_codes k LEFT JOIN products p ON k.product_id=p.id WHERE code=?");
    $s->execute([$code]);
    $k = $s->fetch();
    if ($k) {
        $db->prepare("UPDATE kiz_codes SET scanned_at=NOW(),status='scanned' WHERE id=?")->execute([$k['id']]);
        logIt($db,$uid,$uname,'Пробит КИЗ '.$k['article'],'kiz');
        R(true, ['found' => true, 'product' => ['name' => $k['name'], 'article' => $k['article']]]);
    } else {
        R(true, ['found' => false]);
    }
}

// ========================================================================
// ============ STAFF ============
// ========================================================================
if ($api === 'staff') {
    $rows = $db->query("SELECT * FROM staff ORDER BY id DESC")->fetchAll();
    $active = 0; $vac = 0; $fired = 0;
    foreach ($rows as $r) { 
        if ($r['status']==='active') $active++; 
        elseif ($r['status']==='vacation') $vac++; 
        else $fired++; 
    }
    $today = (int)$db->query("SELECT COUNT(DISTINCT staff_id) FROM time_logs WHERE date=CURDATE() AND check_in IS NOT NULL")->fetchColumn();
    
    $h = '<h1>👥 Сотрудники (StaffPro)</h1>';
    $h .= '<div class="stats">';
    $h .= '<div class="stat"><div class="stat-value">'.count($rows).'</div><div class="stat-label">Всего</div></div>';
    $h .= '<div class="stat"><div class="stat-value">'.$active.'</div><div class="stat-label">Активных</div></div>';
    $h .= '<div class="stat"><div class="stat-value">'.$today.'</div><div class="stat-label">На работе сегодня</div></div>';
    $h .= '<div class="stat"><div class="stat-value">'.$vac.'</div><div class="stat-label">В отпуске</div></div>';
    $h .= '</div>';
    
    $h .= '<div class="card"><h3>👤 Список ';
    $h .= '<button class="btn btn-success" onclick="addStaff()">➕ Добавить</button>';
    $h .= '<button class="btn btn-secondary" onclick="exportStaff()">📥 CSV</button>';
    $h .= '</h3>';
    $h .= '<input type="text" class="search" placeholder="🔍 Поиск по ФИО..." oninput="filterTable(\'staff-tbl\',this.value)">';
    $h .= '<div class="table-scroll"><table id="staff-tbl"><thead><tr><th>№</th><th>ФИО</th><th>Должность</th><th>Телефон</th><th>Email</th><th>Ставка</th><th>Статус</th><th></th></tr></thead><tbody>';
    if (count($rows) == 0) $h .= '<tr><td colspan="8" class="empty">Нет сотрудников</td></tr>';
    foreach ($rows as $i=>$r) {
        $stMap = ['active'=>'✅ Активен','vacation'=>'🏖️ Отпуск','fired'=>'❌ Уволен'];
        $st = isset($stMap[$r['status']]) ? $stMap[$r['status']] : H($r['status']);
        $badge = $r['status']==='active' ? 'badge-success' : ($r['status']==='vacation' ? 'badge-warning' : 'badge-danger');
        $h .= '<tr>';
        $h .= '<td>'.($i+1).'</td>';
        $h .= '<td><strong>'.H($r['name']).'</strong></td>';
        $h .= '<td>'.H($r['position']).'</td>';
        $h .= '<td>'.H($r['phone']??'-').'</td>';
        $h .= '<td>'.H($r['email']??'-').'</td>';
        $h .= '<td>'.number_format($r['rate'],2).' ₽/ч</td>';
        $h .= '<td><span class="badge '.$badge.'">'.$st.'</span></td>';
        $h .= '<td>';
        $h .= '<button class="btn btn-secondary" onclick="editStaff('.$r['id'].')">✏️</button>';
        $h .= '<button class="btn btn-success" onclick="checkIn('.$r['id'].')">✅</button>';
        $h .= '<button class="btn btn-warning" onclick="checkOut('.$r['id'].')">🚪</button>';
        $h .= '<button class="btn btn-danger" onclick="delStaff('.$r['id'].')">🗑</button>';
        $h .= '</td></tr>';
    }
    $h .= '</tbody></table></div></div>';
    
    $h .= '<div class="card"><h3>⏰ Учёт времени (сегодня)</h3>';
    $tl = $db->query("SELECT * FROM time_logs WHERE date=CURDATE() ORDER BY check_in DESC")->fetchAll();
    $h .= '<table><thead><tr><th>Сотрудник</th><th>Приход</th><th>Уход</th><th>Часы</th></tr></thead><tbody>';
    if (count($tl) == 0) $h .= '<tr><td colspan="4" class="empty">Нет записей</td></tr>';
    foreach ($tl as $t) {
        $h .= '<tr>';
        $h .= '<td>'.H($t['staff_name']).'</td>';
        $h .= '<td>'.($t['check_in'] ? date('H:i', strtotime($t['check_in'])) : '-').'</td>';
        $h .= '<td>'.($t['check_out'] ? date('H:i', strtotime($t['check_out'])) : '-').'</td>';
        $h .= '<td>'.$t['hours'].'</td>';
        $h .= '</tr>';
    }
    $h .= '</tbody></table></div>';
    
    R(true, ['html' => $h]);
}
if ($api === 'add_staff') {
    $db->prepare("INSERT INTO staff (name,position,rate,phone,email) VALUES (?,?,?,?,?)")->execute([
        $_POST['name'],$_POST['position']??'',(float)$_POST['rate'],
        $_POST['phone']??'',$_POST['email']??''
    ]);
    logIt($db,$uid,$uname,'Сотрудник: '.$_POST['name'],'staff');
    R(true);
}
if ($api === 'edit_staff') {
    $db->prepare("UPDATE staff SET name=?,position=?,rate=?,phone=?,email=?,status=? WHERE id=?")->execute([
        $_POST['name'],$_POST['position']??'',(float)$_POST['rate'],
        $_POST['phone']??'',$_POST['email']??'',$_POST['status']??'active',(int)$_POST['id']
    ]);
    R(true);
}
if ($api === 'get_staff_item') {
    $s = $db->prepare("SELECT * FROM staff WHERE id=?");
    $s->execute([(int)$_POST['id']]);
    R(true, ['staff' => $s->fetch()]);
}
if ($api === 'del_staff') { 
    $db->prepare("DELETE FROM staff WHERE id=?")->execute([(int)$_POST['id']]); 
    R(true); 
}
if ($api === 'get_all_staff') {
    $staff = $db->query("SELECT * FROM staff ORDER BY id DESC")->fetchAll();
    R(true, ['staff' => $staff]);
}
if ($api === 'check_in') {
    $staffId = (int)$_POST['id'];
    $s = $db->prepare("SELECT name FROM staff WHERE id=?");
    $s->execute([$staffId]);
    $staffName = $s->fetchColumn() ?: 'Сотрудник #'.$staffId;
    
    $existing = $db->prepare("SELECT id FROM time_logs WHERE staff_id=? AND DATE(check_in)=CURDATE() AND check_out IS NULL");
    $existing->execute([$staffId]);
    if ($existing->fetch()) R(false, ['error' => 'Приход уже отмечен сегодня']);
    
    $db->prepare("INSERT INTO time_logs (staff_id,staff_name,date,check_in) VALUES (?,?,CURDATE(),NOW())")
       ->execute([$staffId, $staffName]);
    logIt($db,$uid,$uname,'Приход: '.$staffName,'staff');
    R(true, ['time' => date('H:i:s')]);
}
if ($api === 'check_out') {
    $staffId = (int)$_POST['id'];
    $s = $db->prepare("SELECT * FROM time_logs WHERE staff_id=? AND DATE(check_in)=CURDATE() AND check_out IS NULL ORDER BY id DESC LIMIT 1");
    $s->execute([$staffId]);
    $log = $s->fetch();
    if (!$log) R(false, ['error' => 'Нет активного прихода']);
    $seconds = time() - strtotime($log['check_in']);
    $hours = round($seconds / 3600, 2);
    $db->prepare("UPDATE time_logs SET check_out=NOW(),hours=? WHERE id=?")->execute([$hours, $log['id']]);
    logIt($db,$uid,$uname,'Уход: '.$log['staff_name'].' ('.$hours.' ч.)','staff');
    R(true, ['hours' => $hours, 'name' => $log['staff_name']]);
}

// ========================================================================
// ============ PAYROLL ============
// ========================================================================
if ($api === 'payroll') {
    $staff = $db->query("SELECT * FROM staff WHERE status='active' ORDER BY name")->fetchAll();
    $norm = 160;
    $k1 = 1.5; $k2 = 2.0;
    $totalFOT = 0;
    foreach ($staff as $s) $totalFOT += $s['rate'] * $norm;
    
    $h = '<h1>💰 Ведомость заработной платы</h1>';
    $h .= '<div class="card"><h3>⚙️ Параметры</h3>';
    $h .= '<div class="grid-4">';
    $h .= '<div><label>Месяц</label><input type="month" class="search" id="pay-month" value="'.date('Y-m').'"></div>';
    $h .= '<div><label>Норма часов</label><input type="number" class="search" id="pay-norm" value="'.$norm.'"></div>';
    $h .= '<div><label>Коэф. до 2ч</label><input type="number" class="search" id="pay-k1" value="'.$k1.'" step="0.1"></div>';
    $h .= '<div><label>Коэф. >2ч</label><input type="number" class="search" id="pay-k2" value="'.$k2.'" step="0.1"></div>';
    $h .= '</div></div>';
    
    $h .= '<div class="stats">';
    $h .= '<div class="stat"><div class="stat-value">'.number_format($totalFOT,0,',',' ').' ₽</div><div class="stat-label">💰 ФОТ (месяц)</div></div>';
    $h .= '<div class="stat"><div class="stat-value">'.count($staff).'</div><div class="stat-label">👥 Сотрудников</div></div>';
    $h .= '<div class="stat"><div class="stat-value">'.$norm.'</div><div class="stat-label">⏰ Норма часов</div></div>';
    $h .= '</div>';
    
    $h .= '<div class="card"><h3>👥 Ведомость ';
    $h .= '<button class="btn btn-secondary" onclick="exportPayroll()">📥 CSV</button>';
    $h .= '<button class="btn btn-primary" onclick="printPayroll()">🖨️ Печать</button>';
    $h .= '</h3>';
    $h .= '<div class="table-scroll"><table id="payroll-tbl"><thead><tr><th>№</th><th>ФИО</th><th>Ставка</th><th>Часы</th><th>Сверх.до 2ч</th><th>Сверх.>2ч</th><th>Начислено</th><th>Удержано</th><th>Выплачено</th><th>Сальдо</th></tr></thead><tbody>';
    foreach ($staff as $i=>$s) {
        $acc = $s['rate'] * $norm;
        $h .= '<tr data-staff="'.$s['id'].'" data-rate="'.$s['rate'].'">';
        $h .= '<td>'.($i+1).'</td>';
        $h .= '<td>'.H($s['name']).'</td>';
        $h .= '<td>'.number_format($s['rate'],2).'</td>';
        $h .= '<td><input type="number" class="h-in" style="width:60px;padding:4px" value="'.$norm.'"></td>';
        $h .= '<td><input type="number" class="ot1-in" style="width:60px;padding:4px" value="0"></td>';
        $h .= '<td><input type="number" class="ot2-in" style="width:60px;padding:4px" value="0"></td>';
        $h .= '<td class="accrued">'.number_format($acc,2).' ₽</td>';
        $h .= '<td class="deducted">0 ₽</td>';
        $h .= '<td class="paid">0 ₽</td>';
        $h .= '<td class="saldo">'.number_format($acc,2).' ₽</td>';
        $h .= '</tr>';
    }
    $h .= '</tbody></table></div></div>';
    
    $h .= '<script>initPayroll();</script>';
    
    R(true, ['html' => $h]);
}

// ========================================================================
// ============ CRM ============
// ========================================================================
if ($api === 'crm') {
    $h = '<h1>📋 CRM — Управление операциями</h1>';
    $h .= '<div class="card"><h3>📌 Информация</h3>';
    $h .= '<div class="grid-3">';
    $h .= '<div>📌 <strong>Маршрут:</strong> —</div>';
    $h .= '<div>📦 <strong>SKU:</strong> 0</div>';
    $h .= '<div>📊 <strong>План:</strong> 0</div>';
    $h .= '</div></div>';
    
    $h .= '<div class="card"><h3>🎛️ Параметры</h3>';
    $h .= '<button class="btn btn-primary" onclick="go(\'wb\')">🟣 Wildberries FBO</button>';
    $h .= '<button class="btn btn-secondary" onclick="go(\'ozon\')">🔵 Ozon FBO</button></div>';
    
    $h .= '<div class="card"><h3>📦 Коробка #НОВАЯ</h3>';
    $h .= '<p>📦 Позиций: <strong>0</strong> | ⚖️ <strong>0.00 кг</strong></p>';
    $h .= '<table><thead><tr><th>ШК</th><th>Арт.</th><th>Название</th><th>Кол-во</th></tr></thead><tbody><tr><td colspan="4" class="empty">Пуста</td></tr></tbody></table></div>';
    
    $h .= '<div class="card"><h3>📡 Сканирование</h3>';
    $h .= '<input type="text" class="scan-input" placeholder="Штрих-код..." onkeypress="if(event.key===\'Enter\'){toast(\'Скан: \'+this.value,\'info\');this.value=\'\'}"></div>';
    
    $h .= '<div class="card"><h3>🎯 План (ТЗ)</h3><button class="btn btn-success" onclick="go(\'ozon\')">📥 Перейти к Ozon</button></div>';
    
    $h .= '<div class="card"><h3>📜 Экспорт</h3><button class="btn btn-primary" onclick="toast(\'📤 Экспорт\',\'info\')">📤 CSV</button> <button class="btn btn-danger" onclick="toast(\'🔄 Сброс\',\'info\')">🔄 Сброс</button></div>';
    
    R(true, ['html' => $h]);
}

// ========================================================================
// ============ CHAT ============
// ========================================================================
if ($api === 'chat') {
    $msgs = $db->query("SELECT * FROM messages ORDER BY id DESC LIMIT 100")->fetchAll();
    $users = $db->query("SELECT DISTINCT user_id,user_name FROM messages ORDER BY user_name")->fetchAll();
    
    $mh = '';
    foreach (array_reverse($msgs) as $m) {
        $own = $m['user_id'] == $uid;
        $bg = $own ? '#6c5ce7' : '#f0f0f0';
        $color = $own ? 'white' : 'black';
        $align = $own ? 'text-align:right' : '';
        $mh .= '<div style="margin:10px 0;'.$align.'"><div style="display:inline-block;max-width:70%;padding:12px 16px;border-radius:16px;background:'.$bg.';color:'.$color.'"><div style="font-size:0.85rem;opacity:0.8;margin-bottom:4px"><strong>'.H($m['user_name']).'</strong> • '.date('H:i',strtotime($m['created_at'])).'</div>'.nl2br(H($m['text'])).'</div></div>';
    }
    
    $uh = '';
    foreach ($users as $u) {
        $uh .= '<div style="padding:8px;display:flex;align-items:center;gap:8px"><span style="width:8px;height:8px;background:#00b894;border-radius:50%;display:inline-block"></span>'.H($u['user_name']).'</div>';
    }
    
    $h = '<h1>💬 Чат команды</h1>';
    $h .= '<div class="grid-chat">';
    $h .= '<div class="card chat-main">';
    $h .= '<h3>Общий чат <span class="badge badge-info">'.count($users).' участников</span></h3>';
    $h .= '<div id="chat-msgs" class="chat-messages">'.($mh ?: '<p class="empty" style="margin-top:50px">Нет сообщений. Будьте первым!</p>').'</div>';
    $h .= '<div class="chat-input"><input type="text" id="chat-in" class="search" placeholder="Сообщение..." onkeypress="if(event.key===\'Enter\')sendMsg()" style="margin:0"><button class="btn btn-primary" onclick="sendMsg()">🚀</button></div>';
    $h .= '</div>';
    $h .= '<div class="card"><h3>👥 Участники</h3>'.$uh.'</div>';
    $h .= '</div>';
    
    $h .= '<script>startChatPolling();</script>';
    
    R(true, ['html' => $h]);
}
if ($api === 'chat_refresh') {
    $msgs = $db->query("SELECT * FROM messages ORDER BY id DESC LIMIT 100")->fetchAll();
    $mh = '';
    foreach (array_reverse($msgs) as $m) {
        $own = $m['user_id'] == $uid;
        $bg = $own ? '#6c5ce7' : '#f0f0f0';
        $color = $own ? 'white' : 'black';
        $align = $own ? 'text-align:right' : '';
        $mh .= '<div style="margin:10px 0;'.$align.'"><div style="display:inline-block;max-width:70%;padding:12px 16px;border-radius:16px;background:'.$bg.';color:'.$color.'"><div style="font-size:0.85rem;opacity:0.8;margin-bottom:4px"><strong>'.H($m['user_name']).'</strong> • '.date('H:i',strtotime($m['created_at'])).'</div>'.nl2br(H($m['text'])).'</div></div>';
    }
    R(true, ['html' => $mh]);
}
if ($api === 'send_msg') {
    $text = trim($_POST['text'] ?? '');
    if ($text === '') R(false, ['error' => 'Пустое сообщение']);
    $db->prepare("INSERT INTO messages (user_id,user_name,text) VALUES (?,?,?)")->execute([$uid,$uname,$text]);
    R(true);
}

// ========================================================================
// ============ DEMO (ПРИЁМКА) ============
// ========================================================================
if ($api === 'demo') {
    $plan = (int)$db->query("SELECT COUNT(*) FROM receiving_plan")->fetchColumn();
    $received = (int)$db->query("SELECT COUNT(*) FROM receiving_plan WHERE received_qty>0")->fetchColumn();
    $progress = $plan > 0 ? round($received/$plan*100) : 0;
    
    $h = '<h1>📥 Приёмка товара</h1>';
    $h .= '<div class="stats">';
    $h .= '<div class="stat"><div class="stat-value">'.$plan.'</div><div class="stat-label">Всего</div></div>';
    $h .= '<div class="stat"><div class="stat-value">'.$received.'</div><div class="stat-label">Принято</div></div>';
    $h .= '<div class="stat"><div class="stat-value" id="demo-errors">0</div><div class="stat-label">Ошибки</div></div>';
    $h .= '<div class="stat"><div class="stat-value">'.$progress.'%</div><div class="stat-label">Прогресс</div></div>';
    $h .= '</div>';
    
    $h .= '<div class="card"><h3>📂 Загрузка плана (CSV) <button class="btn btn-secondary" onclick="clearPlan()">🗑 Очистить</button></h3>';
    $h .= '<p class="sub-text">Формат: штрих-код;артикул;название;ожидаемое_количество</p>';
    $h .= '<textarea id="plan-csv" class="search" rows="5" placeholder="46000001;ART-001;Товар 1;100&#10;46000002;ART-002;Товар 2;50"></textarea>';
    $h .= '<button class="btn btn-success" onclick="loadPlan()">📥 Загрузить план</button></div>';
    
    $h .= '<div class="card"><h3>📡 Метод ввода</h3>';
    $h .= '<button class="btn btn-primary">⌨️ Сканер</button>';
    $h .= '<button class="btn btn-secondary">📷 Камера</button>';
    $h .= '<button class="btn btn-secondary">✍️ Вручную</button></div>';
    
    $h .= '<div class="card"><h3>⌨️ Физический сканер (ТСД)</h3>';
    $h .= '<input type="text" id="demo-scan" class="scan-input" placeholder="Сканируйте штрих-код..." onkeypress="if(event.key===\'Enter\')acceptDemo()">';
    $h .= '<button class="btn btn-success btn-full" style="margin-top:10px" onclick="acceptDemo()">⚡ ПРИНЯТЬ КОРОБ</button>';
    $h .= '<div id="demo-result" style="margin-top:15px"></div></div>';
    
    $h .= '<div class="card"><h3>🔊 Звук и голос</h3>';
    $h .= '<div class="grid-2">';
    $h .= '<label><input type="checkbox" checked id="snd-en"> 🎵 Звуки</label>';
    $h .= '<label><input type="checkbox" checked id="voice-en"> 🗣️ Голос</label>';
    $h .= '<label><input type="checkbox" id="digits-en"> 🔢 Цифры словами</label>';
    $h .= '<label><input type="checkbox" id="vibro-en"> 📳 Вибрация</label>';
    $h .= '</div></div>';
    
    $h .= '<div class="card"><h3>📋 Журнал операций</h3>';
    $h .= '<div id="demo-log" class="demo-log"><div>['.date('H:i:s').'] Система готова. Голос озвучивает последние цифры штрихкода</div></div></div>';
    
    $h .= '<div class="card"><h3>⚙️ Режимы</h3>';
    $h .= '<label><input type="checkbox"> 🙈 Слепая приёмка</label> ';
    $h .= '<label><input type="checkbox" checked> ✅ Выборочный контроль</label></div>';
    
    R(true, ['html' => $h]);
}
if ($api === 'load_plan') {
    $csv = $_POST['csv'] ?? '';
    $lines = array_filter(explode("\n", str_replace("\r",'',$csv)));
    $db->exec("DELETE FROM receiving_plan");
    $count = 0;
    foreach ($lines as $line) {
        $line = trim($line);
        if (!$line) continue;
        $parts = explode(';', $line);
        if (count($parts) < 4) continue;
        $db->prepare("INSERT INTO receiving_plan (barcode,article,name,expected_qty) VALUES (?,?,?,?)")->execute([
            $parts[0],$parts[1],$parts[2],(int)$parts[3]
        ]);
        $count++;
    }
    logIt($db,$uid,$uname,'Загружен план: '.$count,'demo');
    R(true, ['count' => $count]);
}
if ($api === 'clear_plan') { 
    $db->exec("DELETE FROM receiving_plan"); 
    R(true); 
}
if ($api === 'accept_demo') {
    $barcode = trim($_POST['barcode']);
    $s = $db->prepare("SELECT * FROM receiving_plan WHERE barcode=?");
    $s->execute([$barcode]);
    $p = $s->fetch();
    if ($p) {
        $db->prepare("UPDATE receiving_plan SET received_qty=received_qty+1,status='received' WHERE id=?")->execute([$p['id']]);
        logIt($db,$uid,$uname,'Принято: '.$p['article'],'demo');
        R(true, ['found' => true, 'product' => $p]);
    } else {
        R(true, ['found' => false, 'barcode' => $barcode]);
    }
}

// ========================================================================
// ============ SETTINGS ============
// ========================================================================
if ($api === 'settings') {
    $users = $db->query("SELECT email,name,role,created_at FROM users ORDER BY created_at")->fetchAll();
    $settings = [];
    foreach ($db->query("SELECT * FROM settings")->fetchAll() as $s) $settings[$s['key']] = $s['value'];
    
    $uh = '';
    foreach ($users as $u) {
        $uh .= '<tr><td>'.H($u['email']).'</td><td>'.H($u['name']).'</td><td><span class="badge badge-info">'.H($u['role']).'</span></td><td>'.date('d.m.Y',strtotime($u['created_at'])).'</td></tr>';
    }
    
    $h = '<h1>⚙️ Настройки системы</h1>';
    $h .= '<div class="card"><h3>🏢 Организация</h3>';
    $h .= '<div class="grid-2">';
    $h .= '<div><label>Название</label><input type="text" class="search" id="set-company" value="'.H($settings['company_name']??'').'"></div>';
    $h .= '<div><label>Адрес</label><input type="text" class="search" id="set-addr" value="'.H($settings['company_address']??'').'"></div>';
    $h .= '<div><label>Телефон</label><input type="text" class="search" id="set-phone" value="'.H($settings['company_phone']??'').'"></div>';
    $h .= '<div><label>Норма часов</label><input type="number" class="search" id="set-norm" value="'.H($settings['work_hours_norm']??'160').'"></div>';
    $h .= '</div>';
    $h .= '<button class="btn btn-success" style="margin-top:15px" onclick="saveSettings()">💾 Сохранить</button></div>';
    
    $h .= '<div class="card"><h3>🔗 Интеграции</h3>';
    $h .= '<div class="grid-3">';
    $h .= '<div class="integration-card"><h4>📊 1С</h4><p class="sub-text">Интеграция с 1С:Предприятие</p><button class="btn btn-secondary">🔌 Подключить</button></div>';
    $h .= '<div class="integration-card"><h4>📄 Диадок</h4><p class="sub-text">Электронный документооборот</p><button class="btn btn-secondary">🔌 Подключить</button></div>';
    $h .= '<div class="integration-card"><h4>🏭 WMS</h4><p class="sub-text">Warehouse Management</p><button class="btn btn-secondary">🔌 Подключить</button></div>';
    $h .= '</div></div>';
    
    $h .= '<div class="card"><h3>💾 Данные</h3>';
    $h .= '<p><strong>База:</strong> '.DB_NAME.' | <strong>Хост:</strong> '.DB_HOST.'</p>';
    $h .= '<button class="btn btn-primary" onclick="exportAll()">📥 Экспорт JSON</button>';
    $h .= '<button class="btn btn-danger" onclick="if(confirm(\'Удалить ВСЕ данные?\'))resetAll()">🗑 Сброс</button></div>';
    
    $h .= '<div class="card"><h3>👥 Пользователи ('.count($users).')</h3>';
    $h .= '<table><thead><tr><th>Email</th><th>Имя</th><th>Роль</th><th>Дата</th></tr></thead><tbody>'.$uh.'</tbody></table></div>';
    
    $h .= '<div class="card"><h3>ℹ️ О системе</h3>';
    $h .= '<p><strong>FulFillPro</strong> v13.0</p>';
    $h .= '<p>Модулей: 13 | PHP: '.PHP_VERSION.'</p>';
    $h .= '<p>База: MySQL | Хранение: Сервер</p></div>';
    
    R(true, ['html' => $h]);
}
if ($api === 'save_settings') {
    $data = json_decode($_POST['data'] ?? '{}', true);
    if (!is_array($data)) R(false, ['error' => 'Неверные данные']);
    $stmt = $db->prepare("INSERT INTO settings (`key`,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
    foreach ($data as $k => $v) $stmt->execute([$k, $v]);
    logIt($db,$uid,$uname,'Настройки сохранены','settings');
    R(true);
}
if ($api === 'reset_all') {
    $tables = ['products','staff','shipments','messages','kiz_codes','logs','time_logs','receiving_plan','delivery_pallets','tz_items','boxes','box_items'];
    foreach ($tables as $t) {
        try { $db->exec("TRUNCATE TABLE $t"); } catch(Exception $e) {}
    }
    logIt($db,$uid,$uname,'Сброшены все данные','settings');
    R(true);
}
if ($api === 'export_all') {
    $data = [];
    $tables = ['products','staff','shipments','messages','kiz_codes','time_logs','tz_items','boxes'];
    foreach ($tables as $t) $data[$t] = $db->query("SELECT * FROM $t")->fetchAll();
    R(true, ['data' => $data]);
}

R(false, ['error' => 'Unknown API: '.$api]);
