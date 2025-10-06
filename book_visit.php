<?php require_once __DIR__ . '/includes/layout.php'; renderHeader('Request a Visit'); ?>
<main style="max-width:720px;margin:20px auto;">
  <div id="app">
    <h2>Request a Visit</h2>
    <div v-if="err" style="background:#fdecea;border:1px solid #f5c2c7;padding:10px;border-radius:6px;margin-bottom:12px;">{{ err }}</div>
    <div v-if="ok"  style="background:#ecfdf3;border:1px solid #badbcc;padding:10px;border-radius:6px;margin-bottom:12px;">Request submitted (PENDING).</div>

    <form @submit.prevent="submit" style="display:grid;gap:10px;">
      <label>Resident
        <select v-model.number="resident_user_id" required>
          <option value="">-- Select resident --</option>
          <option v-for="r in residents" :value="r.id">{{ r.full_name }}</option>
        </select>
      </label>
      <label>Date <input type="date" v-model="date" required /></label>
      <label>Time <input type="time" v-model="time" required /></label>
      <label>Notes (optional) <textarea rows="3" v-model="notes"></textarea></label>
      <button type="submit">Submit Request</button>
    </form>
  </div>
</main>
<script src="https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.7/dist/axios.min.js"></script>
<script src="assets/app.js"></script>
<script>
Vue.createApp({
  data(){
    return {
      me: null,
      loading: true,
      residents: [],
      resident_user_id: '',
      date: '',
      time: '',
      notes: '',
      err: '',
      ok: false
    };
  },
  methods:{
    msg(e){
      return (e && e.response && e.response.data && e.response.data.error && e.response.data.error.message)
        ? e.response.data.error.message
        : (e && e.message ? e.message : 'Unexpected error');
    },
    async ensureCsrf(){
      try { await apiGet('/csrf', { _ts: Date.now() }); } catch(e){}
    },
    async loadMe(){
      const r = await apiGet('/me', { _ts: Date.now() });
      this.me = (r.data && r.data.data) ? r.data.data.user : null;
    },
    async loadResidents(){
      // Only residents that THIS visitor is linked to
      const r = await apiGet('/me/related-residents', { _ts: Date.now() });
      const payload = r.data && r.data.data ? r.data.data : {};
      // API returns { items:[...] } — but tolerate { residents:[...] } too
      this.residents = payload.items || payload.residents || [];
    },
    combine(){
      if (!this.date || !this.time) return null;
      return this.date + ' ' + (this.time.length === 5 ? this.time + ':00' : this.time);
    },
    async submit(){
      this.err = ''; this.ok = false;
      const requested_start = this.combine();
      if (!this.me || this.me.role !== 'VISITOR') { this.err = 'Visitors only. Please sign in as a visitor.'; return; }
      if (!this.resident_user_id) { this.err = 'Please select a resident.'; return; }
      if (!requested_start) { this.err = 'Invalid date/time.'; return; }

      try{
        await apiPost('/visit-requests', {
          resident_user_id: this.resident_user_id,
          requested_start: requested_start,
          notes: this.notes || null
        });
        this.ok = true;
        this.notes = '';
      }catch(e){
        this.err = this.msg(e);
      }
    }
  },
  async mounted(){
    try{
      // 🔐 fetch CSRF and set X-CSRF-Token on axios
      await window.apiGetCsrf();

      await this.loadMe();
      if (!this.me || this.me.role !== 'VISITOR') {
        this.err = 'Visitors only. Please sign in as a visitor.';
        return;
      }
      await this.loadResidents();
      if (this.residents.length === 0) {
        this.err = 'No linked residents found. Add a relationship first (Admin/Staff via Relationships page).';
      }
    }catch(e){
      this.err = this.msg(e);
    }finally{
      this.loading = false;
    }
  }
}).mount('#app');
</script>
<?php renderFooter(); ?>
