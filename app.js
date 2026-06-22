// ========================================
// ============ БАЗОВЫЕ ФУНКЦИИ ============
// ========================================

function toast(m, type) {
    var t = document.createElement('div');
    t.className = 'toast';
    t.textContent = m;
    if (type === 'success') t.style.borderLeftColor = '#00b894';
    else if (type === 'error') t.style.borderLeftColor = '#e17055';
    else if (type === 'warning') t.style.borderLeftColor = '#fdcb6e';
    else t.style.borderLeftColor = '#6c5ce7';
    document.body.appendChild(t);
    setTimeout(function(){ t.remove(); }, 3000);
}

function api(f) {
    return fetch('system.php', {method:'POST', body:f})
        .then(function(r){ return r.text(); })
        .then(function(t){
            try { return JSON.parse(t); }
            catch(e) { return {ok:false, error:'Сервер: '+t.substring(0,200)}; }
        });
}

function go(m, el) {
    if (el) {
        document.querySelectorAll('.ni').forEach(function(i){ i.classList.remove('active'); });
        el.classList.add('active');
    }
    var f = new FormData();
    f.append('api', m);
    api(f).then(function(d){
        if (d.ok) {
            document.getElementById('main').innerHTML = d.html;
            document.getElementById('main').querySelectorAll('script').forEach(function(s){
                var n = document.createElement('script');
                n.text = s.text;
                document.head.appendChild(n);
            });
        } else {
            document.getElementById('main').innerHTML = '<h1>Ошибка: '+d.error+'</h1>';
        }
    });
}

function logout() {
    var f = new FormData();
    f.append('api', 'logout');
    api(f).then(function(){ location.reload(); });
}

function filterTable(id, q) {
    q = q.toLowerCase();
    var tbl = document.getElementById(id);
    if (!tbl) return;
    tbl.querySelectorAll('tbody tr').forEach(function(r){
        r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

// ========================================
// ============ DASHBOARD ============
// ========================================
function drawDashboardChart(data) {
    var c = document.getElementById('dsh-chart');
    if (!c) return;
    var x = c.getContext('2d');
    var w = c.width = c.offsetWidth;
    var h = 200;
    var max = Math.max.apply(null, data.map(function(d){return d.count})) * 1.2 || 1;
    var pad = 40;
    x.clearRect(0, 0, w, h);
    
    // Сетка
    x.strokeStyle = '#ecf0f1';
    x.lineWidth = 1;
    for (var i = 0; i <= 4; i++) {
        var y = pad + i * ((h - pad*2) / 4);
        x.beginPath();
        x.moveTo(pad, y);
        x.lineTo(w - pad, y);
        x.stroke();
    }
    
    // Линия
    x.strokeStyle = '#6c5ce7';
    x.lineWidth = 3;
    x.beginPath();
    data.forEach(function(d, i){
        var px = pad + (i * (w - pad*2) / (data.length - 1));
        var py = h - pad - (d.count / max * (h - pad*2));
        if (i === 0) x.moveTo(px, py);
        else x.lineTo(px, py);
    });
    x.stroke();
    
    // Точки и подписи
    x.fillStyle = '#6c5ce7';
    data.forEach(function(d, i){
        var px = pad + (i * (w - pad*2) / (data.length - 1));
        var py = h - pad - (d.count / max * (h - pad*2));
        x.beginPath();
        x.arc(px, py, 5, 0, Math.PI*2);
        x.fill();
        x.fillStyle = '#636e72';
        x.font = '12px sans-serif';
        x.textAlign = 'center';
        var dn = ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'][new Date(d.date).getDay()];
        x.fillText(dn, px, h - 10);
        x.fillText(d.count, px, py - 12);
        x.fillStyle = '#6c5ce7';
    });
}

// ========================================
// ============ WMS ============
// ========================================
function addProduct() {
    var n = prompt('Название:'); if (!n) return;
    var a = prompt('Артикул:') || '';
    var b = prompt('Штрих-код:') || '';
    var q = parseInt(prompt('Количество:') || '0');
    var l = prompt('Место хранения:') || '';
    var mq = parseInt(prompt('Мин. количество:') || '10');
    var p = parseFloat(prompt('Цена (₽):') || '0');
    var exp = prompt('Срок годности (YYYY-MM-DD):') || '';
    var f = new FormData();
    f.append('api','add_product');
    f.append('name',n); f.append('article',a); f.append('barcode',b);
    f.append('quantity',q); f.append('location',l);
    f.append('min_quantity',mq); f.append('price',p); f.append('expiry',exp);
    api(f).then(function(d){
        if (d.ok) { toast('✅ Добавлено','success'); go('wms'); }
        else toast('❌ '+d.error,'error');
    });
}

function editProduct(id) {
    var f = new FormData();
    f.append('api','get_product'); f.append('id',id);
    api(f).then(function(d){
        if (!d.ok) return;
        var p = d.product;
        var n = prompt('Название:', p.name); if (n === null) return;
        var a = prompt('Артикул:', p.article) || '';
        var b = prompt('ШК:', p.barcode) || '';
        var q = parseInt(prompt('Количество:', p.quantity) || '0');
        var l = prompt('Место:', p.location) || '';
        var mq = parseInt(prompt('Мин:', p.min_quantity) || '10');
        var pr = parseFloat(prompt('Цена:', p.price) || '0');
        var exp = prompt('Срок:', p.expiry) || '';
        var f2 = new FormData();
        f2.append('api','edit_product');
        f2.append('id',id); f2.append('name',n); f2.append('article',a);
        f2.append('barcode',b); f2.append('quantity',q); f2.append('location',l);
        f2.append('min_quantity',mq); f2.append('price',pr); f2.append('expiry',exp);
        api(f2).then(function(d2){
            if (d2.ok) { toast('✅ Обновлено','success'); go('wms'); }
        });
    });
}

function delProduct(id) {
    if (!confirm('Удалить товар?')) return;
    var f = new FormData();
    f.append('api','del_product'); f.append('id',id);
    api(f).then(function(){ toast('Удалено','success'); go('wms'); });
}

function scanWMS() {
    var s = document.getElementById('wms-scan');
    if (!s.value.trim()) return;
    var f = new FormData();
    f.append('api','scan_wms'); f.append('barcode',s.value);
    api(f).then(function(d){
        var r = document.getElementById('scan-result');
        if (d.found) {
            r.innerHTML = '<div class="alert" style="background:#d4edda;color:#155724">✅ <strong>'+d.product.name+'</strong><br>Арт: '+d.product.article+'<br>Кол-во: '+d.product.quantity+'<br>Место: '+d.product.location+'</div>';
            toast('Найден: '+d.product.name,'success');
        } else {
            r.innerHTML = '<div class="alert">❌ Не найден</div>';
            toast('Не найден','error');
        }
        s.value = ''; s.focus();
    });
}

function exportWMS() {
    var f = new FormData();
    f.append('api','export_wms');
    api(f).then(function(d){
        if (!d.ok) return;
        var csv = 'Артикул;Название;ШК;Кол-во;Место;Цена;Срок\n';
        d.data.forEach(function(r){
            csv += '"'+r.article+'";"'+r.name+'";"'+r.barcode+'";'+r.quantity+';"'+r.location+'";'+r.price+';"'+(r.expiry||'')+'"\n';
        });
        var blob = new Blob(['\ufeff'+csv], {type:'text/csv;charset=utf-8'});
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url; a.download = 'products_'+Date.now()+'.csv'; a.click();
        toast('📥 CSV скачан','success');
    });
}

function importWMS() { toast('Используйте ТЗ модули для импорта','info'); }

// ========================================
// ============ FBO ============
// ========================================
function addShipment() {
    var p = prompt('Платформа (ozon/wb):') || 'ozon';
    var b = parseInt(prompt('Коробов:') || '0');
    var pl = parseInt(prompt('Палет:') || '0');
    var w = parseFloat(prompt('Вес (кг):') || '0');
    var s = prompt('Статус (new/processing/done):') || 'new';
    var t = prompt('Трекинг:') || '';
    var f = new FormData();
    f.append('api','add_shipment');
    f.append('platform',p); f.append('boxes',b); f.append('pallets',pl);
    f.append('weight',w); f.append('status',s); f.append('tracking',t);
    api(f).then(function(d){
        if (d.ok) { toast('✅ Создано','success'); go('fbo'); }
        else toast(d.error,'error');
    });
}

function delShipment(id) {
    if (!confirm('Удалить?')) return;
    var f = new FormData();
    f.append('api','del_shipment'); f.append('id',id);
    api(f).then(function(){ toast('Удалено','success'); go('fbo'); });
}

function filterShip(p) {
    document.querySelectorAll('#ship-tbl tbody tr').forEach(function(r){
        if (p === 'all') r.style.display = '';
        else r.style.display = r.dataset.platform === p ? '' : 'none';
    });
}

// ========================================
// ============ OZON / WB ============
// ========================================
function importCSV(platform) {
    var csv = document.getElementById(platform+'-csv').value;
    if (!csv.trim()) { toast('Введите CSV','error'); return; }
    var f = new FormData();
    f.append('api','import_csv');
    f.append('platform',platform); f.append('csv',csv);
    api(f).then(function(d){
        if (d.ok) { toast('✅ Загружено: '+d.count+' позиций','success'); go(platform); }
        else toast(d.error,'error');
    });
}

function clearTZ(platform) {
    if (!confirm('Очистить ТЗ '+platform+'?')) return;
    var f = new FormData();
    f.append('api','clear_tz'); f.append('platform',platform);
    api(f).then(function(){ toast('Очищено','success'); go(platform); });
}

function delTZ(id) {
    if (!confirm('Удалить?')) return;
    var f = new FormData();
    f.append('api','del_tz'); f.append('id',id);
    api(f).then(function(){ toast('Удалено','success'); go('ozon'); });
}

function printLabels(platform, type) {
    var f = new FormData();
    f.append('api','get_labels');
    f.append('platform',platform); f.append('type',type);
    api(f).then(function(d){
        if (!d.ok || d.labels.length === 0) { toast('Нет этикеток','error'); return; }
        toast('🖨️ Печать '+d.labels.length+' этикеток','success');
        var pw = window.open('','_blank');
        var color = platform === 'ozon' ? '#005bff' : '#a020f0';
        var html = '<!DOCTYPE html><html><head><title>Печать</title><style>';
        html += '@page{size:A4;margin:10mm}';
        html += 'body{font-family:Arial;margin:0;padding:10mm}';
        html += '.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:5mm}';
        html += '.label{border:1px solid #000;padding:8mm;background:white;page-break-inside:avoid}';
        html += '.hd{display:flex;justify-content:space-between;font-size:10px;color:#666;margin-bottom:3mm}';
        html += '.logo{color:'+color+';font-weight:bold;font-size:14px}';
        html += '.bc{text-align:center;margin:3mm 0;font-family:"Courier New";font-size:14px;letter-spacing:2px}';
        html += '.art{font-weight:bold;font-size:12px;margin:2mm 0}';
        html += '.nm{font-size:11px;margin:2mm 0;line-height:1.3}';
        html += '.ft{display:flex;justify-content:space-between;margin-top:3mm;font-size:10px}';
        html += '.pr{font-weight:bold;font-size:13px}';
        html += '@media print{.no-print{display:none}}';
        html += '.btn{position:fixed;top:10px;right:10px;padding:10px 20px;background:'+color+';color:white;border:none;border-radius:6px;cursor:pointer;font-size:16px}';
        html += '</style></head><body>';
        html += '<button class="btn no-print" onclick="window.print()">🖨️ Печать</button>';
        html += '<div class="grid">';
        d.labels.forEach(function(l){
            html += '<div class="label">';
            html += '<div class="hd"><span class="logo">'+platform.toUpperCase()+'</span><span>'+l.number+'/'+l.total+'</span></div>';
            html += '<div class="art">Арт: '+l.article+'</div>';
            html += '<div class="nm">'+l.name+'</div>';
            html += '<div class="bc">||| '+l.barcode+' |||</div>';
            html += '<div class="ft"><span class="pr">'+l.price.toFixed(2)+' ₽</span><span>'+platform.toUpperCase()+' FBO</span></div>';
            html += '</div>';
        });
        html += '</div></body></html>';
        pw.document.write(html);
        pw.document.close();
    });
}

function printOneLabel(platform, id) {
    var f = new FormData();
    f.append('api','get_labels');
    f.append('platform',platform); f.append('type','all');
    api(f).then(function(d){
        if (!d.ok || d.labels.length === 0) return;
        var l = d.labels.find(function(x){return x.id == id}) || d.labels[0];
        if (!l) return;
        var color = platform === 'ozon' ? '#005bff' : '#a020f0';
        var pw = window.open('','_blank');
        var html = '<!DOCTYPE html><html><head><title>Этикетка</title><style>';
        html += 'body{font-family:Arial;padding:20mm}';
        html += '.label{border:2px solid #000;padding:15mm;max-width:200mm}';
        html += '.logo{color:'+color+';font-size:24px;font-weight:bold}';
        html += '.bc{font-family:"Courier New";font-size:20px;text-align:center;margin:10mm 0}';
        html += '.nm{font-size:16px;margin:5mm 0}';
        html += '@media print{button{display:none}}';
        html += '</style></head><body>';
        html += '<button onclick="window.print()">🖨️ Печать</button>';
        html += '<div class="label">';
        html += '<div class="logo">'+platform.toUpperCase()+'</div>';
        html += '<div class="nm"><strong>Арт:</strong> '+l.article+'</div>';
        html += '<div class="nm">'+l.name+'</div>';
        html += '<div class="bc">||| '+l.barcode+' |||</div>';
        html += '<div class="nm"><strong>Цена:</strong> '+l.price.toFixed(2)+' ₽</div>';
        html += '</div></body></html>';
        pw.document.write(html);
        pw.document.close();
    });
}

function resetLabels(platform) {
    if (!confirm('Сбросить статус?')) return;
    var f = new FormData();
    f.append('api','reset_labels'); f.append('platform',platform);
    api(f).then(function(){ toast('Сброшено','success'); go(platform); });
}

function createBox(platform) {
    var pallet = parseInt(prompt('Номер палета:','1') || '1');
    var f = new FormData();
    f.append('api','create_box');
    f.append('platform',platform); f.append('pallet',pallet);
    api(f).then(function(d){
        if (d.ok) { toast('✅ Короб #'+d.box_number,'success'); go(platform); }
        else toast(d.error,'error');
    });
}

function scanToBox(boxId) {
    var scan = prompt('Штрих-код (или артикул):');
    if (!scan) return;
    var f = new FormData();
    f.append('api','scan_to_box');
    f.append('box_id',boxId); f.append('barcode',scan);
    api(f).then(function(d){
        if (d.ok) {
            toast('✅ '+d.product.name+' в короб #'+d.product.box,'success');
            var curMod = document.querySelector('.ni.active').textContent.toLowerCase();
            go(curMod.includes('ozon') ? 'ozon' : 'wb');
        } else {
            toast('❌ '+d.error,'error');
        }
    });
}

function closeBox(id) {
    if (!confirm('Закрыть короб?')) return;
    var f = new FormData();
    f.append('api','close_box'); f.append('id',id);
    api(f).then(function(d){
        if (d.ok) { toast('✅ Закрыт. ШК: '+d.barcode,'success'); go('ozon'); }
        else toast(d.error,'error');
    });
}

function delBox(id) {
    if (!confirm('Удалить короб?')) return;
    var f = new FormData();
    f.append('api','del_box'); f.append('id',id);
    api(f).then(function(){ toast('Удалено','success'); go('ozon'); });
}

function downloadCSVTemplate() {
    var csv = 'Артикул;Штрих-код;Название;Количество;Коробов;Палет;Цена\n';
    csv += 'ART-001;4600000000001;Товар 1;100;10;1;299.90\n';
    csv += 'ART-002;4600000000002;Товар 2;50;5;1;599.00\n';
    var blob = new Blob(['\ufeff'+csv], {type:'text/csv;charset=utf-8'});
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url; a.download = 'TZ_Template.csv'; a.click();
    toast('📊 Шаблон скачан','success');
}

function exportReport(platform) {
    var f = new FormData();
    f.append('api','export_report'); f.append('platform',platform);
    api(f).then(function(d){
        if (!d.ok) return;
        var csv = 'ОТЧЁТ '+platform.toUpperCase()+'\n\n';
        csv += 'Позиций: '+d.tz.length+'\nКоробов: '+d.boxes.length+'\n\n';
        csv += 'ТОВАРЫ:\nАртикул;ШК;Название;Кол-во;Цена\n';
        d.tz.forEach(function(t){
            csv += t.article+';'+t.barcode+';'+t.name+';'+t.quantity+';'+t.price+'\n';
        });
        csv += '\nКОРОБА:\n№;Палет;Вес;Позиций;ШК;Статус\n';
        d.boxes.forEach(function(b){
            csv += b.box_number+';'+b.pallet_number+';'+b.weight+';'+b.items_count+';'+(b.barcode||'')+';'+b.status+'\n';
        });
        var blob = new Blob(['\ufeff'+csv], {type:'text/csv;charset=utf-8'});
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url; a.download = platform+'_Report_'+Date.now()+'.csv'; a.click();
        toast('📥 Отчёт','success');
    });
}

function marketplaceScan(platform) {
    var s = document.getElementById(platform+'-scan');
    if (!s.value.trim()) return;
    toast('Скан: '+s.value,'info');
    s.value = ''; s.focus();
}

// ========================================
// ============ DELIVERY ============
// ========================================
function drawTruck(pallets) {
    var c = document.getElementById('truck-canvas');
    if (!c) return;
    var x = c.getContext('2d');
    var w = c.width = c.offsetWidth;
    var h = 300;
    x.clearRect(0, 0, w, h);
    
    // Кузов
    x.strokeStyle = '#2d3436';
    x.lineWidth = 3;
    x.strokeRect(10, 10, w-20, h-20);
    
    // Подписи
    x.fillStyle = '#636e72';
    x.font = '14px sans-serif';
    x.textAlign = 'center';
    x.fillText('13600 мм', w/2, h-2);
    x.save();
    x.translate(5, h/2);
    x.rotate(-Math.PI/2);
    x.fillText('2450 мм', 0, 0);
    x.restore();
    
    // Палеты
    var startX = 30;
    var palletW = 80;
    var palletH = 120;
    var gap = 5;
    var row = 0;
    pallets.forEach(function(p){
        var x2 = startX + (row * (palletW + gap));
        var y2 = 30;
        if (x2 + palletW > w - 30) {
            row = 0;
            x2 = startX;
            y2 = 170;
        }
        x.fillStyle = p.platform === 'wb' ? '#a020f0' : '#005bff';
        x.globalAlpha = 0.7;
        x.fillRect(x2, y2, palletW, palletH);
        x.globalAlpha = 1;
        x.strokeStyle = '#000';
        x.strokeRect(x2, y2, palletW, palletH);
        x.fillStyle = 'white';
        x.font = 'bold 12px sans-serif';
        x.textAlign = 'center';
        x.fillText(p.platform.toUpperCase(), x2 + palletW/2, y2 + palletH/2);
        x.font = '10px sans-serif';
        x.fillText(p.weight+'кг', x2 + palletW/2, y2 + palletH/2 + 15);
        row++;
    });
}

function addPallet(platform) {
    var f = new FormData();
    f.append('api','add_pallet'); f.append('platform',platform);
    api(f).then(function(){ toast('Палет добавлен','success'); go('delivery'); });
}

function delPallet(id) {
    if (!confirm('Удалить?')) return;
    var f = new FormData();
    f.append('api','del_pallet'); f.append('id',id);
    api(f).then(function(){ toast('Удалено','success'); go('delivery'); });
}

function clearPallets() {
    if (!confirm('Очистить все?')) return;
    var f = new FormData();
    f.append('api','clear_pallets');
    api(f).then(function(){ toast('Очищено','success'); go('delivery'); });
}

function autoBalance() {
    var f = new FormData();
    f.append('api','auto_balance');
    api(f).then(function(){ toast('Баланс выполнен','success'); go('delivery'); });
}

// ========================================
// ============ KIZ ============
// ========================================
function saveKIZ() {
    var p = document.getElementById('kiz-prod').value;
    var c = document.getElementById('kiz-codes').value;
    if (!p || !c.trim()) { toast('Заполните поля','error'); return; }
    var f = new FormData();
    f.append('api','save_kiz');
    f.append('product_id',p); f.append('codes',c);
    api(f).then(function(d){
        if (d.ok) { toast('✅ Привязано: '+d.count,'success'); go('kiz'); }
        else toast(d.error,'error');
    });
}

function importKIZ() {
    var csv = document.getElementById('kiz-csv').value;
    if (!csv.trim()) { toast('Введите CSV','error'); return; }
    var f = new FormData();
    f.append('api','import_kiz'); f.append('csv',csv);
    api(f).then(function(d){
        if (d.ok) { toast('✅ Импортировано: '+d.count,'success'); go('kiz'); }
        else toast(d.error,'error');
    });
}

function delKIZ(id) {
    if (!confirm('Удалить?')) return;
    var f = new FormData();
    f.append('api','del_kiz'); f.append('id',id);
    api(f).then(function(){ toast('Удалено','success'); go('kiz'); });
}

function scanKIZ() {
    var s = document.getElementById('kiz-scan');
    if (!s.value.trim()) return;
    var f = new FormData();
    f.append('api','scan_kiz'); f.append('code',s.value);
    api(f).then(function(d){
        if (d.found) {
            toast('✅ '+d.product.name+' ('+d.product.article+')','success');
            if ('speechSynthesis' in window) {
                var u = new SpeechSynthesisUtterance('Пробит '+d.product.article);
                u.lang = 'ru-RU';
                speechSynthesis.speak(u);
            }
        } else {
            toast('❌ Код не найден','error');
        }
        s.value = ''; s.focus();
    });
}

// ========================================
// ============ STAFF ============
// ========================================
function addStaff() {
    var n = prompt('ФИО:'); if (!n) return;
    var p = prompt('Должность:') || '';
    var r = parseFloat(prompt('Ставка (₽/ч):') || '0');
    var ph = prompt('Телефон:') || '';
    var e = prompt('Email:') || '';
    var f = new FormData();
    f.append('api','add_staff');
    f.append('name',n); f.append('position',p); f.append('rate',r);
    f.append('phone',ph); f.append('email',e);
    api(f).then(function(d){
        if (d.ok) { toast('✅ Добавлен','success'); go('staff'); }
        else toast('❌ '+d.error,'error');
    });
}

function editStaff(id) {
    var f = new FormData();
    f.append('api','get_staff_item'); f.append('id',id);
    api(f).then(function(d){
        if (!d.ok) return;
        var s = d.staff;
        var n = prompt('ФИО:', s.name); if (n === null) return;
        var p = prompt('Должность:', s.position || '') || '';
        var r = parseFloat(prompt('Ставка:', s.rate) || '0');
        var ph = prompt('Телефон:', s.phone || '') || '';
        var e = prompt('Email:', s.email || '') || '';
        var st = prompt('Статус (active/vacation/fired):', s.status || 'active') || 'active';
        var f2 = new FormData();
        f2.append('api','edit_staff');
        f2.append('id',id); f2.append('name',n); f2.append('position',p);
        f2.append('rate',r); f2.append('phone',ph); f2.append('email',e); f2.append('status',st);
        api(f2).then(function(){ toast('Обновлено','success'); go('staff'); });
    });
}

function delStaff(id) {
    if (!confirm('Удалить?')) return;
    var f = new FormData();
    f.append('api','del_staff'); f.append('id',id);
    api(f).then(function(){ toast('Удалено','success'); go('staff'); });
}

function checkIn(id) {
    var f = new FormData();
    f.append('api','check_in'); f.append('id',id);
    api(f).then(function(d){
        if (d.ok) { toast('✅ Приход отмечен в '+d.time,'success'); go('staff'); }
        else toast('❌ '+d.error,'error');
    });
}

function checkOut(id) {
    if (!confirm('Отметить уход?')) return;
    var f = new FormData();
    f.append('api','check_out'); f.append('id',id);
    api(f).then(function(d){
        if (d.ok) { toast('🚪 '+d.name+' ушёл. Часов: '+d.hours,'success'); go('staff'); }
        else toast('❌ '+d.error,'error');
    });
}

function exportStaff() {
    var f = new FormData();
    f.append('api','get_all_staff');
    api(f).then(function(d){
        if (!d.ok) return;
        var csv = '№;ФИО;Должность;Телефон;Email;Ставка;Статус\n';
        d.staff.forEach(function(s, i){
            csv += (i+1)+';"'+s.name+'";"'+(s.position||'')+'";"'+(s.phone||'')+'";"'+(s.email||'')+'";'+s.rate+';'+s.status+'\n';
        });
        var blob = new Blob(['\ufeff'+csv], {type:'text/csv;charset=utf-8'});
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url; a.download = 'Staff_'+Date.now()+'.csv'; a.click();
        toast('📥 CSV','success');
    });
}

// ========================================
// ============ PAYROLL ============
// ========================================
function initPayroll() {
    function recalc() {
        var norm = parseFloat(document.getElementById('pay-norm').value) || 160;
        var k1 = parseFloat(document.getElementById('pay-k1').value) || 1.5;
        var k2 = parseFloat(document.getElementById('pay-k2').value) || 2;
        document.querySelectorAll('#payroll-tbl tbody tr').forEach(function(r){
            var rate = parseFloat(r.dataset.rate) || 0;
            var h = parseFloat(r.querySelector('.h-in').value) || 0;
            var o1 = parseFloat(r.querySelector('.ot1-in').value) || 0;
            var o2 = parseFloat(r.querySelector('.ot2-in').value) || 0;
            var acc = rate*h + rate*o1*k1 + rate*o2*k2;
            r.querySelector('.accrued').textContent = acc.toLocaleString('ru-RU') + ' ₽';
        });
    }
    document.querySelectorAll('#payroll-tbl input').forEach(function(i){
        i.addEventListener('input', recalc);
    });
    document.getElementById('pay-norm').addEventListener('input', recalc);
    document.getElementById('pay-k1').addEventListener('input', recalc);
    document.getElementById('pay-k2').addEventListener('input', recalc);
}

function exportPayroll() {
    var rows = document.querySelectorAll('#payroll-tbl tbody tr');
    var csv = '№;ФИО;Ставка;Часы;Сверх.до 2ч;Сверх.>2ч;Начислено\n';
    rows.forEach(function(r, i){
        var tds = r.querySelectorAll('td');
        csv += (i+1)+';'+tds[1].textContent+';'+tds[2].textContent+';'+r.querySelector('.h-in').value+';'+r.querySelector('.ot1-in').value+';'+r.querySelector('.ot2-in').value+';'+r.querySelector('.accrued').textContent+'\n';
    });
    var blob = new Blob(['\ufeff'+csv], {type:'text/csv;charset=utf-8'});
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url; a.download = 'Payroll_'+Date.now()+'.csv'; a.click();
    toast('📥 CSV','success');
}

function printPayroll() { window.print(); }

// ========================================
// ============ CHAT ============
// ========================================
var chatInterval = null;
function startChatPolling() {
    if (chatInterval) clearInterval(chatInterval);
    chatInterval = setInterval(function(){
        fetch('system.php', {method:'POST', body:'api=chat_refresh'})
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d.ok) {
                    var e = document.getElementById('chat-msgs');
                    if (!e) return;
                    var was = e.scrollHeight - e.scrollTop - e.clientHeight < 50;
                    e.innerHTML = d.html;
                    if (was) e.scrollTop = e.scrollHeight;
                }
            });
    }, 3000);
}

function sendMsg() {
    var i = document.getElementById('chat-in');
    if (!i.value.trim()) return;
    var f = new FormData();
    f.append('api','send_msg'); f.append('text',i.value);
    api(f).then(function(d){
        if (d.ok) { i.value = ''; go('chat'); }
        else toast(d.error,'error');
    });
}

// ========================================
// ============ DEMO ============
// ========================================
function loadPlan() {
    var csv = document.getElementById('plan-csv').value;
    if (!csv.trim()) { toast('Введите CSV','error'); return; }
    var f = new FormData();
    f.append('api','load_plan'); f.append('csv',csv);
    api(f).then(function(d){
        if (d.ok) { toast('✅ План: '+d.count+' позиций','success'); go('demo'); }
        else toast(d.error,'error');
    });
}

function clearPlan() {
    if (!confirm('Очистить?')) return;
    var f = new FormData();
    f.append('api','clear_plan');
    api(f).then(function(){ toast('Очищено','success'); go('demo'); });
}

function acceptDemo() {
    var s = document.getElementById('demo-scan');
    if (!s.value.trim()) return;
    var f = new FormData();
    f.append('api','accept_demo'); f.append('barcode',s.value);
    api(f).then(function(d){
        var log = document.getElementById('demo-log');
        var ts = new Date().toLocaleTimeString();
        if (d.found) {
            log.innerHTML = '<div>['+ts+'] ✅ Принято: '+d.product.article+' ('+d.product.name+')</div>' + log.innerHTML;
            toast('✅ '+d.product.name,'success');
            var voiceEn = document.getElementById('voice-en');
            if (voiceEn && voiceEn.checked && 'speechSynthesis' in window) {
                var last4 = d.product.barcode.slice(-4);
                var u = new SpeechSynthesisUtterance('Принято. Последние цифры: '+last4);
                u.lang = 'ru-RU';
                speechSynthesis.speak(u);
            }
        } else {
            var errs = parseInt(document.getElementById('demo-errors').textContent) + 1;
            document.getElementById('demo-errors').textContent = errs;
            log.innerHTML = '<div style="color:#e17055">['+ts+'] ❌ Не найдено: '+d.barcode+'</div>' + log.innerHTML;
            toast('❌ Не найден','error');
        }
        s.value = ''; s.focus();
    });
}

// ========================================
// ============ SETTINGS ============
// ========================================
function saveSettings() {
    var data = {
        company_name: document.getElementById('set-company').value,
        company_address: document.getElementById('set-addr').value,
        company_phone: document.getElementById('set-phone').value,
        work_hours_norm: document.getElementById('set-norm').value
    };
    var f = new FormData();
    f.append('api','save_settings');
    f.append('data',JSON.stringify(data));
    api(f).then(function(){ toast('💾 Сохранено','success'); });
}

function resetAll() {
    var f = new FormData();
    f.append('api','reset_all');
    api(f).then(function(){
        toast('🗑 Сброшено','success');
        setTimeout(function(){ location.reload(); }, 1000);
    });
}

function exportAll() {
    var f = new FormData();
    f.append('api','export_all');
    api(f).then(function(d){
        if (!d.ok) return;
        var blob = new Blob([JSON.stringify(d.data,null,2)], {type:'application/json'});
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url; a.download = 'FulFillPro_Backup_'+Date.now()+'.json'; a.click();
        toast('📥 Backup','success');
    });
}
