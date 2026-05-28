const STORAGE_KEY = 'cosmetic_wms_pro_v1';

const state = {
  users: [
    {login:'admin', pass:'1234', role:'admin'},
    {login:'manager', pass:'1234', role:'manager'},
    {login:'picker', pass:'1234', role:'picker'},
    {login:'viewer', pass:'1234', role:'viewer'}
  ],
  currentUser: null,
  products: [],
  locations: [],
  batches: [],
  orders: [],
  movements: []
};

const uid = () => Math.random().toString(36).slice(2, 10);
const now = () => new Date();
const dateStr = d => d.toISOString().slice(0,10);
const logEl = document.getElementById('log');

function log(msg) {
  logEl.innerHTML = `[${new Date().toLocaleString()}] ${msg}<br>` + logEl.innerHTML;
}

function persist() {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

function restore() {
  const raw = localStorage.getItem(STORAGE_KEY);
  if (!raw) return false;
  const saved = JSON.parse(raw);
  state.currentUser = saved.currentUser || null;
  state.products = saved.products || [];
  state.locations = saved.locations || [];
  state.batches = saved.batches || [];
  state.orders = saved.orders || [];
  state.movements = saved.movements || [];
  state.users = saved.users || state.users;
  return true;
}

function saveToLocal() {
  persist();
  log('Данные сохранены');
}

function loadFromLocal() {
  if (!restore()) return alert('Нет сохранённых данных');
  log('Данные загружены');
  render();
}

function exportJSON() {
  const blob = new Blob([JSON.stringify(state, null, 2)], {type:'application/json'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'cosmetic_wms_data.json';
  a.click();
}

function resetAll() {
  if (!confirm('Сбросить все данные?')) return;
  state.products = [];
  state.locations = [];
  state.batches = [];
  state.orders = [];
  state.movements = [];
  state.currentUser = null;
  localStorage.removeItem(STORAGE_KEY);
  currentUser.value = 'Не авторизован';
  log('Система сброшена');
  render();
}

function login() {
  const u = loginUser.value.trim();
  const p = loginPass.value;
  const r = loginRole.value;
  const found = state.users.find(x => x.login === u && x.pass === p && x.role === r);
  if (!found) return alert('Неверный логин/пароль/роль');
  state.currentUser = found;
  currentUser.value = `${found.login} (${found.role})`;
  log(`Вход: ${found.login} / ${found.role}`);
  render();
}

function logout() {
  state.currentUser = null;
  currentUser.value = 'Не авторизован';
  log('Выход из системы');
  render();
}

function can(roleList) {
  if (!state.currentUser) return false;
  return roleList.includes(state.currentUser.role);
}

function addProduct() {
  if (!can(['admin','manager'])) return alert('Нет доступа');
  const p = {
    id: uid(),
    name: pName.value.trim(),
    sku: pSku.value.trim(),
    category: pCat.value.trim(),
    unit: pUnit.value.trim(),
    minStock: +pMin.value,
    maxStock: +pMax.value,
    shelfLifeDays: +pShelfLife.value,
    paoDays: +pPao.value
  };
  if (!p.name || !p.sku) return alert('Заполните название и SKU');
  state.products.push(p);
  log(`Товар добавлен: ${p.name}`);
  render();
}

function addLocation() {
  if (!can(['admin','manager'])) return alert('Нет доступа');
  const l = {
    id: uid(),
    zone: lZone.value.trim(),
    aisle: lAisle.value.trim(),
    shelf: lShelf.value.trim(),
    position: lPos.value.trim(),
    type: lType.value,
    capacity: +lCap.value,
    usage: 0
  };
  if (!l.zone || !l.aisle) return alert('Заполните зону и проход');
  state.locations.push(l);
  log(`Локация добавлена: ${l.zone}-${l.aisle}-${l.shelf}-${l.position}`);
  render();
}

function addBatch(productId, locationId, qty, lot, expiryDate) {
  let b = state.batches.find(x => x.productId === productId && x.locationId === locationId && x.lot === lot);
  if (!b) {
    b = {
      id: uid(),
      productId,
      locationId,
      lot,
      qty: 0,
      reserved: 0,
      receivedAt: dateStr(now()),
      expiryDate,
      status: 'active'
    };
    state.batches.push(b);
  }
  b.qty += qty;
  const loc = state.locations.find(x => x.id === locationId);
  if (loc) loc.usage += qty;
  return b;
}

function getProductStock(productId) {
  return state.batches.filter(b => b.productId === productId).reduce((s,b) => s + (b.qty - b.reserved), 0);
}

function receiveBatch() {
  if (!can(['admin','manager'])) return alert('Нет доступа');
  if (!moveProduct.value || !moveLocation.value) return alert('Выберите товар и локацию');
  if (!batchLot.value.trim()) return alert('Введите партию');
  if (!expiryDate.value) return alert('Укажите срок годности');
  const qty = +moveQty.value;
  const prod = state.products.find(x => x.id === moveProduct.value);
  const exp = new Date(expiryDate.value);
  if (Number.isNaN(exp.getTime())) return alert('Неверная дата');
  const daysLeft = Math.ceil((exp - new Date()) / 86400000);
  if (daysLeft < 0) return alert('Срок годности уже истёк');
  if (daysLeft > prod.shelfLifeDays + 30) {
    log('Внимание: срок годности выглядит подозрительно длинным для данного товара');
  }
  addBatch(moveProduct.value, moveLocation.value, qty, batchLot.value.trim(), expiryDate.value);
  state.movements.push({
    type:'inbound',
    productId: moveProduct.value,
    locationId: moveLocation.value,
    lot: batchLot.value.trim(),
    expiryDate: expiryDate.value,
    qty,
    user: moveUser.value,
    ts: new Date().toISOString()
  });
  log(`Приёмка партии ${batchLot.value.trim()} (${qty} ед.)`);
  render();
}

function availableBatchesForProduct(productId) {
  return state.batches
    .filter(b => b.productId === productId && b.qty - b.reserved > 0)
    .sort((a,b) => new Date(a.expiryDate) - new Date(b.expiryDate));
}

function shipFromFEFO() {
  if (!can(['admin','manager','picker'])) return alert('Нет доступа');
  const productId = moveProduct.value;
  const qtyNeed = +moveQty.value;
  let remaining = qtyNeed;
  const batches = availableBatchesForProduct(productId);
  if (!batches.length) return alert('Нет доступного товара');
  for (const b of batches) {
    const free = b.qty - b.reserved;
    const take = Math.min(free, remaining);
    b.qty -= take;
    const loc = state.locations.find(x => x.id === b.locationId);
    if (loc) loc.usage -= take;
    if (b.qty <= 0) b.status = 'empty';
    state.movements.push({
      type:'outbound',
      productId,
      locationId: b.locationId,
      lot: b.lot,
      expiryDate: b.expiryDate,
      qty: take,
      user: moveUser.value,
      ts: new Date().toISOString(),
      method: 'FEFO'
    });
    log(`FEFO отбор: ${take} ед. из партии ${b.lot} со сроком ${b.expiryDate}`);
    remaining -= take;
    if (remaining <= 0) break;
  }
  if (remaining > 0) alert('Недостаточно товара для полного списания');
  state.batches = state.batches.filter(b => b.qty > 0);
  render();
}

function transferBatch() {
  if (!can(['admin','manager'])) return alert('Нет доступа');
  const fromId = moveLocation.value;
  const to = state.locations.find(x => x.id !== fromId);
  if (!to) return alert('Нужна вторая локация');
  const qty = +moveQty.value;
  const b = state.batches.find(x => x.productId === moveProduct.value && x.locationId === fromId && x.qty >= qty);
  if (!b) return alert('Партия не найдена или недостаточно товара');
  b.qty -= qty;
  const from = state.locations.find(x => x.id === fromId);
  if (from) from.usage -= qty;
  if (b.qty <= 0) state.batches = state.batches.filter(x => x !== b);
  addBatch(moveProduct.value, to.id, qty, b.lot, b.expiryDate);
  state.movements.push({
    type:'transfer',
    productId: moveProduct.value,
    fromId,
    toId: to.id,
    lot: b.lot,
    qty,
    user: moveUser.value,
    ts: new Date().toISOString()
  });
  log(`Трансфер партии ${b.lot}: ${qty} ед. -> ${to.zone}-${to.aisle}-${to.shelf}-${to.position}`);
  render();
}

function createOrder() {
  if (!can(['admin','manager'])) return alert('Нет доступа');
  let items;
  try {
    items = JSON.parse(oItems.value || '{}');
  } catch {
    return alert('Неверный JSON');
  }
  const o = {
    id: uid(),
    customer: oCustomer.value.trim(),
    items,
    status: 'pending',
    createdAt: new Date().toISOString()
  };
  if (!o.customer) return alert('Введите клиента');
  state.orders.push(o);
  orderId.value = o.id;
  log(`Заказ создан: ${o.id}`);
  render();
}

function pickOrder() {
  if (!can(['admin','manager','picker'])) return alert('Нет доступа');
  const order = state.orders.find(x => x.id === orderId.value);
  if (!order) return alert('Заказ не найден');
  order.status = 'picking';
  log(`Заказ собран: ${order.id}`);
  render();
}

function shipOrder() {
  if (!can(['admin','manager'])) return alert('Нет доступа');
  const order = state.orders.find(x => x.id === orderId.value);
  if (!order) return alert('Заказ не найден');
  order.status = 'shipped';
  log(`Заказ отгружен: ${order.id}`);
  render();
}

function searchProducts() {
  const q = searchText.value.trim().toLowerCase();
  const res = state.products.filter(p =>
    p.name.toLowerCase().includes(q) ||
    p.sku.toLowerCase().includes(q) ||
    p.category.toLowerCase().includes(q)
  );
  reportBox.innerHTML = res.length
    ? `<b>Найдено:</b><br>${res.map(p => `${p.name} (${p.sku})`).join('<br>')}`
    : 'Ничего не найдено';
}

function showLowStock() {
  const low = state.products
    .map(p => ({p, stock: getProductStock(p.id)}))
    .filter(x => x.stock <= x.p.minStock);
  reportBox.innerHTML = low.length
    ? `<b>Низкий остаток:</b><br>${low.map(x => `${x.p.name}: ${x.stock}`).join('<br>')}`
    : 'Низких остатков нет';
}

function showExpiring() {
  const threshold = +expiryThreshold.value;
  const today = new Date();
  const list = state.batches.map(b => {
    const d = new Date(b.expiryDate);
    const days = Math.ceil((d - today) / 86400000);
    return {...b, daysLeft: days};
  }).filter(b => b.daysLeft <= threshold && b.daysLeft >= 0)
    .sort((a,b) => a.daysLeft - b.daysLeft);
  reportBox.innerHTML = list.length
    ? `<b>Скоро истекает:</b><br>${list.map(b => {
        const p = state.products.find(x => x.id === b.productId);
        return `${p ? p.name : ''} / партия ${b.lot} / ${b.daysLeft} дн.`;
      }).join('<br>')}`
    : 'Нет партий с истекающим сроком в указанном диапазоне';
}

function renderInventory() {
  const tbody = document.querySelector('#inventoryTable tbody');
  const today = new Date();
  tbody.innerHTML = state.batches.map(b => {
    const p = state.products.find(x => x.id === b.productId) || {};
    const l = state.locations.find(x => x.id === b.locationId) || {};
    const daysLeft = Math.ceil((new Date(b.expiryDate) - today) / 86400000);
    let status = '<span class="ok">OK</span>';
    if (daysLeft < 0) status = '<span class="exp">EXPIRED</span>';
    else if (daysLeft <= +expiryThreshold.value) status = '<span class="low">EXPIRING</span>';
    return `<tr>
      <td>${p.name || ''}</td>
      <td>${p.sku || ''}</td>
      <td>${b.lot}</td>
      <td>${l.zone || ''}-${l.aisle || ''}-${l.shelf || ''}-${l.position || ''}</td>
      <td>${b.qty}</td>
      <td>${b.expiryDate}</td>
      <td>${status}</td>
    </tr>`;
  }).join('');
}

function updateStats() {
  statProducts.textContent = state.products.length;
  statLocations.textContent = state.locations.length;
  statBatches.textContent = state.batches.length;
  statStock.textContent = state.batches.reduce((s,b) => s + b.qty, 0);
}

function updateSelects() {
  moveProduct.innerHTML = state.products.map(p => `<option value="${p.id}">${p.name}</option>`).join('');
  moveLocation.innerHTML = state.locations.map(l => `<option value="${l.id}">${l.zone}-${l.aisle}-${l.shelf}-${l.position}</option>`).join('');
}

function renderUser() {
  currentUser.value = state.currentUser ? `${state.currentUser.login} (${state.currentUser.role})` : 'Не авторизован';
}

function render() {
  updateStats();
  updateSelects();
  renderUser();
  renderInventory();
  persist();
}

function loadDemo() {
  state.products = [];
  state.locations = [];
  state.batches = [];
  state.orders = [];
  state.movements = [];
  state.currentUser = state.users[0];

  const p1 = {id: uid(), name:'Крем для лица', sku:'CRM-FACE-001', category:'Уход', unit:'шт', minStock:10, maxStock:100, shelfLifeDays:730, paoDays:365};
  const p2 = {id: uid(), name:'Шампунь', sku:'SHP-HAIR-001', category:'Уход за волосами', unit:'шт', minStock:20, maxStock:200, shelfLifeDays:1095, paoDays:365};
  const l1 = {id: uid(), zone:'A', aisle:'1', shelf:'1', position:'1', type:'storage', capacity:100, usage:0};
  const l2 = {id: uid(), zone:'A', aisle:'2', shelf:'1', position:'1', type:'storage', capacity:100, usage:0};
  state.products.push(p1, p2);
  state.locations.push(l1, l2);

  addBatch(p1.id, l1.id, 50, 'LOT-CRM-001', '2027-06-30');
  addBatch(p1.id, l2.id, 20, 'LOT-CRM-002', '2026-08-15');
  addBatch(p2.id, l2.id, 120, 'LOT-SHP-001', '2028-01-20');

  log('Демо данные загружены');
  render();
}

if (!restore()) state.currentUser = state.users[0];
renderUser();
renderInventory();
updateStats();
updateSelects();

