<?php
require_once __DIR__ . '/includes/layout.php';
renderHeader('Home');
?>
<main style="max-width:900px;margin:24px auto;padding:16px;">
  <div id="app">
    <div v-if="loading">Loading…</div>

    <template v-else>
      <div v-if="me">
        <p style="margin:0 0 12px;">Hi <strong>{{ me.full_name }}</strong> ({{ me.role }})!</p>
        <p>
          <a v-if="me.role==='RESIDENT'" href="./my_services.php">My Services</a>
          <a v-else-if="me.role==='STAFF'" href="./roster.php">My Roster</a>
          <a v-else-if="me.role==='VISITOR'" href="./book_visit.php">Book a Visit</a>
          <span v-else>Welcome!</span>
        </p>
        <button @click="logout" style="padding:8px 12px;border:none;border-radius:6px;background:#555;color:#fff;cursor:pointer;">
          Log out
        </button>
      </div>

      <div v-else>
        <p>Welcome, guest 👋 — you can browse public content here.</p>
        <p><a href="./login.php">Sign in</a> to access your dashboard.</p>
      </div>
    </template>
  </div>
</main>

<script src="https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.7/dist/axios.min.js"></script>
<script src="./assets/app.js"></script>
<script>
Vue.createApp({
  data(){ return { loading:true, me:null, err:'' }; },
  async mounted(){
    try {
      const r = await window.apiGet('/me');     // GET doesn’t need CSRF
      this.me = (r.data && r.data.data && r.data.data.user) ? r.data.data.user : null;
    } catch (_) {
      this.me = null; // not logged in is fine
    } finally {
      // fetch CSRF for future POSTs (optional)
      try { await window.apiGetCsrf(); } catch(e){}
      this.loading = false;
    }
  },
  methods:{
    async logout(){ try { await window.apiPost('/auth/logout', {}); } catch(e){} location.reload(); }
  }
}).mount('#app');
</script>
<?php renderFooter(); ?>
