(function(){
  var guessBase = location.pathname.replace(/\/[^\/]*$/, ''); // drop filename
  window.APP_BASE = window.APP_BASE || guessBase;             // e.g. "/v2"
  window.API_BASE = window.API_BASE || (window.APP_BASE + '/api');

  // One shared axios instance for the whole site
  const api = window._api = axios.create({
    baseURL: API_BASE,
    withCredentials: true
  });

  async function apiGetCsrf() {
  const r = await api.get('/index.php', { params:{ r:'/v1/csrf', __ts:Date.now() } });
  const token = r.data && r.data.data ? r.data.data.csrf : '';
  if (token) api.defaults.headers.common['X-CSRF-Token'] = token;
  return token;
}

  // Attach helpers to window
  function cfgWithPath(path, cfg){
    var base = { params: { r: '/v1' + path, __ts: Date.now() } };
    return cfg ? (function(){
      cfg.params = cfg.params || {};
      for (var k in base.params){ cfg.params[k] = base.params[k]; }
      return cfg;
    })() : base;
  }

  window.apiGet    = function(path, cfg){ return api.get('/index.php',   cfgWithPath(path, cfg)); };
  window.apiPost   = function(path, data, cfg){ return api.post('/index.php', data, cfgWithPath(path, cfg)); };
  window.apiPut    = function(path, data, cfg){ return api.put('/index.php',  data, cfgWithPath(path, cfg)); };
  window.apiDelete = function(path, cfg){ return api.delete('/index.php',     cfgWithPath(path, cfg)); };

  window.apiGetCsrf = function(){
    return api.get('/index.php', { params: { r: '/v1/csrf', __ts: Date.now() } })
      .then(function(r){
        var csrf = r && r.data && r.data.data ? r.data.data.csrf : '';
        if (csrf) api.defaults.headers.common['X-CSRF-Token'] = csrf;
        return csrf;
      });
  };
})();
