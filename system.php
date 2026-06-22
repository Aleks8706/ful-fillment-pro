<?php
ob_start();
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===== ПОДКЛЮЧЕНИЕ К БД =====
$db = null;
$isInstalled = file_exists(__DIR__ . '/config.php');

if ($isInstalled) {
    require __DIR__ . '/config.php';
    try {
        $db = new PDO(
            "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (Exception $e) {
        die('DB Error: ' . $e->getMessage());
    }
}

// ===== ПЕРЕНАПРАВЛЕНИЕ API ЗАПРОСОВ =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api'])) {
    require __DIR__ . '/api.php';
    exit;
}

// ===== HTML SHELL =====
$isLogged = isset($_SESSION['uid']);
$uname = isset($_SESSION['uname']) ? $_SESSION['uname'] : '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FulFillPro v13</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php if (!$isInstalled): ?>
<!-- ==================== УСТАНОВЩИК ==================== -->
<div class="installer-wrap">
    <div class="installer-box">
        <h1>📦 FulFillPro v13.0</h1>
        <p class="sub">Установка системы управления складом</p>
        <div id="msg"></div>
        <div class="field"><label>Хост MySQL</label><input id="h" value="localhost"></div>
        <div class="field"><label>База данных</label><input id="n" value="fulfillpro"></div>
        <div class="field"><label>Пользователь</label><input id="u" value="root"></div>
        <div class="field"><label>Пароль</label><input id="p" type="password"></div>
        <button class="btn-install" onclick="doInstall()">🚀 Установить систему</button>
        <div class="hint-install">
            <strong>После установки войдите с:</strong><br>
            📧 admin@fulfill.pro / admin123<br>
            📧 manager@fulfill.pro / manager123<br>
            📧 worker@fulfill.pro / worker123
        </div>
    </div>
</div>
<script>
function doInstall(){
    var f=new FormData();
    f.append('api','do_install');
    f.append('host',document.getElementById('h').value);
    f.append('dbname',document.getElementById('n').value);
    f.append('dbuser',document.getElementById('u').value);
    f.append('dbpass',document.getElementById('p').value);
    document.getElementById('msg').innerHTML='<div class="msg-info">⏳ Установка...</div>';
    fetch('system.php',{method:'POST',body:f})
        .then(function(r){return r.text()})
        .then(function(t){
            try{
                var d=JSON.parse(t);
                if(d.ok){
                    document.getElementById('msg').innerHTML='<div class="msg-ok">✅ Готово! Перезагрузка...</div>';
                    setTimeout(function(){location.reload()},1500);
                } else {
                    document.getElementById('msg').innerHTML='<div class="msg-err">❌ '+d.error+'</div>';
                }
            }catch(e){
                document.getElementById('msg').innerHTML='<div class="msg-err">'+t.substring(0,500)+'</div>';
            }
        });
}
</script>

<?php elseif (!$isLogged): ?>
<!-- ==================== ЭКРАН ВХОДА ==================== -->
<div class="auth">
    <div class="auth-box">
        <h1>📦 FulFillPro</h1>
        <h2>Единая система управления складом</h2>
        <div id="err"></div>
        <form id="lf">
            <input type="email" id="em" placeholder="Email" value="admin@fulfill.pro" required>
            <input type="password" id="pw" placeholder="Пароль" value="admin123" required>
            <button type="submit" class="btn-login">Войти →</button>
        </form>
        <p class="hint">admin@fulfill.pro / admin123</p>
    </div>
</div>
<script src="app.js"></script>
<script>
document.getElementById('lf').onsubmit=function(e){
    e.preventDefault();
    var f=new FormData();
    f.append('api','login');
    f.append('email',document.getElementById('em').value);
    f.append('password',document.getElementById('pw').value);
    fetch('system.php',{method:'POST',body:f})
        .then(function(r){return r.text()})
        .then(function(t){
            try{
                var d=JSON.parse(t);
                if(d.ok) location.reload();
                else document.getElementById('err').innerHTML='<div class="alert">'+d.error+'</div>';
            }catch(e){
                document.getElementById('err').innerHTML='<div class="alert">'+t.substring(0,500)+'</div>';
            }
        });
};
</script>

<?php else: ?>
<!-- ==================== ОСНОВНОЙ ИНТЕРФЕЙС ==================== -->
<div class="sidebar">
    <h2>📦 FulFillPro</h2>
    <div class="ni active" onclick="go('dashboard',this)"><span>📊</span>Дашборд</div>
    <div class="ni" onclick="go('wms',this)"><span>🏭</span>Склад (WMS)</div>
    <div class="ni" onclick="go('fbo',this)"><span>📤</span>FBO Отгрузки</div>
    <div class="ni" onclick="go('ozon',this)"><span>🔵</span>Ozon</div>
    <div class="ni" onclick="go('wb',this)"><span>🟣</span>Wildberries</div>
    <div class="ni" onclick="go('delivery',this)"><span>🚛</span>Логистика</div>
    <div class="ni" onclick="go('kiz',this)"><span>🏷️</span>КИЗ / ЧЗ</div>
    <div class="ni" onclick="go('staff',this)"><span>👥</span>Сотрудники</div>
    <div class="ni" onclick="go('payroll',this)"><span>💰</span>Зарплата</div>
    <div class="ni" onclick="go('crm',this)"><span>📋</span>CRM</div>
    <div class="ni" onclick="go('chat',this)"><span>💬</span>Чат</div>
    <div class="ni" onclick="go('demo',this)"><span>📥</span>Приёмка</div>
    <div class="ni" onclick="go('settings',this)"><span>⚙️</span>Настройки</div>
    <div class="ni ni-logout" onclick="logout()"><span>🚪</span>Выход (<?=htmlspecialchars($uname)?>)</div>
</div>
<div class="main" id="main">
    <h1>Загрузка...</h1>
</div>
<script src="app.js"></script>
<script>go('dashboard');</script>
<?php endif; ?>

</body>
</html>
