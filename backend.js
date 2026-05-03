// Пример для 1С
function sync1C(){
  fetch('https://your-api.com/api/1c/sync',{
    method:'POST',
    headers:{'Authorization':'Bearer YOUR_TOKEN'},
    body:JSON.stringify({products:DB.get('products')})
  })
  .then(function(r){return r.json();})
  .then(function(data){
    // Обновите данные в DB
    renderWarehouse();
    toast('1С синхронизирована');
  })
  .catch(function(e){toast('Ошибка: '+e.message,'error');});
}