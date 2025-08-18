<?php require_once __DIR__ . '/includes/layout.php'; renderHeader('Login'); ?>
<main style="max-width:420px;margin:40px auto;padding:16px;border:1px solid #eee;border-radius:8px;">
  <div id="app">
    <h2>Sign in</h2>

    <section style="background:#f4f4f4;padding:10px;margin-bottom:15px;border-radius:6px;">
      <p><strong>Current roles are:</strong></p>
      <ul style="margin:5px 0 10px 20px;">
        <li>ADMIN – <code>admin:admin</code></li>
        <li>STAFF – <code>staff:staff</code></li>
        <li>RESIDENT – <code>resident:resident</code></li>
        <li>VISITOR – <code>visitor:visitor</code></li>
      </ul>
    </section>

    <div v-if="err" style="background:#fdecea;border:1px solid #f5c2c7;color:#b00020;padding:10px;border-radius:6px;margin-bottom:12px;">{{ err }}</div>

    <form @submit.prevent="submit" style="display:grid;gap:10px;">
      <input v-model.trim="username" placeholder="Username" autofocus />
      <input v-model="password" type="password" placeholder="Password" />
      <button type="submit" style="padding:10px;border:none;border-radius:6px;background:#2b6cb0;color:#fff;cursor:pointer;">Log in</button>
    </form>
  </div>
</main>

<script>
  // Make APP_BASE available for redirects
  var APP_BASE = "<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); ?>";
</script>
<script src="https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.7/dist/axios.min.js"></script>
<script src="/assets/app.js"></script>
<script>
Vue.createApp({
  data(){ return { username:'', password:'', err:'' }; },
  methods:{
    async submit(){
      this.err='';
      if(!this.username || !this.password){
        this.err='Please enter username and password.';
        return;
      }
      try{
        // Use the helper; it handles base URL & cookies
        const r = await apiPost('/auth/login', { username:this.username, password:this.password });
        const env = r.data || {};
        const payload = env.data || {};
        const user = payload.user;

        if(!user) throw new Error((env.error && env.error.message) || 'Invalid API response');

        // Seed CSRF for this tab (don’t reference `api` directly here)
        try { if (window.apiGetCsrf) { await window.apiGetCsrf(); } } catch(e){ /* ignore */ }

        // Redirect by role
        if (user.role === 'RESIDENT')      location = APP_BASE + '/my_services.php';
        else if (user.role === 'STAFF')    location = APP_BASE + '/roster.php';
        else if (user.role === 'VISITOR')  location = APP_BASE + '/book_visit.php';
        else                               location = APP_BASE + '/index.php';
      } catch(e){
        this.err = (e.response && e.response.data && e.response.data.error && e.response.data.error.message) || e.message;
      }
    }
  }
}).mount('#app');
</script>
<?php renderFooter(); ?>
