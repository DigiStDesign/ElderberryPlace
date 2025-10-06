<?php require_once __DIR__ . '/includes/layout.php'; renderHeader('My Visitations'); ?>
<main style="max-width:960px;margin:20px auto;">
  <div id="app">
    <h2>My Visitations</h2>
    <p>Approve/decline pending requests, cancel approved.</p>
    <div v-if="flash" style="background:#ecfdf3;border:1px solid #badbcc;padding:10px;border-radius:8px;margin-bottom:12px;">{{ flash }}</div>
    <div v-if="err"   style="background:#fdecea;border:1px solid #f5c2c7;padding:10px;border-radius:8px;margin-bottom:12px;">{{ err }}</div>
    <div v-if="rows.length===0" style="background:#eef2ff;border:1px solid #c7d2fe;padding:12px;border-radius:8px;">No visit requests yet.</div>
    <div v-else style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;">
        <thead><tr><th>Requested Start</th><th>Visitor</th><th>Status</th><th>Notes</th><th>Actions</th></tr></thead>
        <tbody>
          <tr v-for="r in rows">
            <td>{{ fmt(r.requested_start) }}</td>
            <td>{{ r.visitor_name }}</td>
            <td><span v-html="badge(r.status)"></span></td>
            <td>{{ r.notes }}</td>
            <td>
              <button v-if="r.status==='PENDING'" @click="act(r,'APPROVE')">Approve</button>
              <button v-if="r.status==='PENDING'" @click="act(r,'DECLINE')">Decline</button>
              <button v-if="r.status==='APPROVED'" @click="act(r,'CANCEL')">Cancel</button>
              <em v-if="r.status!=='PENDING' && r.status!=='APPROVED'">No actions</em>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</main>
<script src="https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.7/dist/axios.min.js"></script>
<script src="assets/app.js"></script>
<script>
Vue.createApp({
  data(){return{rows:[],err:'',flash:''};},
  methods:{
    msg(e){ return (e.response&&e.response.data&&e.response.data.error&&e.response.data.error.message)||e.message; },
    fmt(s){ return s? new Date(s.replace(' ','T')).toLocaleString():''; },
    badge(s){ var map={PENDING:'#fff3cd',APPROVED:'#d1e7dd',DECLINED:'#f8d7da',CANCELLED:'#e2e3e5'}; var bg=map[s]||'#e2e3e5'; return '<span style="padding:2px 8px;border-radius:10px;background:'+bg+'">'+s+'</span>'; },

    async load(){
      this.err='';
      try{
        const r = await apiGet('/me/visitations');
        this.rows = (r.data && r.data.data) ? r.data.data : [];
      }catch(e){ this.err = this.msg(e); }
    },

    async act(row, action){
      this.err=''; this.flash='';
      try{
        await apiPost('/visit-requests/'+row.id+'/status', { action: action });
        this.flash='Updated request #'+row.id+' to '+action;
        this.load();
      }catch(e){
        // If CSRF failed, refresh token and retry once
        const code = e.response && e.response.data && e.response.data.error && e.response.data.error.code;
        if (code === 'CSRF') {
          try{
            await window.apiGetCsrf(); // sets X-CSRF-Token header globally
            await apiPost('/visit-requests/'+row.id+'/status', { action: action });
            this.flash='Updated request #'+row.id+' to '+action;
            this.load();
            return;
          }catch(e2){ this.err = this.msg(e2); return; }
        }
        this.err = this.msg(e);
      }
    }
  },
  async mounted(){
    try {
      await window.apiGetCsrf(); 
      await this.load();
    } catch(e) {
      this.err = this.msg(e);
    }
  }
}).mount('#app');
</script>

<?php renderFooter(); ?>
