<?php require_once __DIR__ . '/includes/layout.php'; renderHeader('My Services'); ?>
<main style="max-width:960px;margin:20px auto;">
  <div id="app">
    <h2>My Services</h2>
    <div style="margin:8px 0 16px;">
      <button @click="load('all')"      :style="btn('all')">All</button>
      <button @click="load('upcoming')" :style="btn('upcoming')">Upcoming</button>
      <button @click="load('completed')" :style="btn('completed')">Completed</button>
    </div>
    <div v-if="err" style="color:#b00020">{{err}}</div>
    <div v-else-if="rows.length===0" style="background:#eef2ff;border:1px solid #c7d2fe;padding:12px;border-radius:8px;">No services found.</div>
    <div v-else style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;">
        <thead><tr><th>Service</th><th>Start</th><th>End</th><th>Location</th><th>Status</th><th>Notes</th></tr></thead>
        <tbody>
          <tr v-for="r in rows">
            <td>{{ r.service_name }}</td>
            <td>{{ fmt(r.start_time) }}</td>
            <td>{{ fmt(r.end_time) }}</td>
            <td>{{ r.location }}</td>
            <td><span v-html="badge(r)"></span></td>
            <td>{{ r.notes }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</main>
<script src="https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.7/dist/axios.min.js"></script>
<script src="/assets/app.js"></script>
<script>
Vue.createApp({
  data(){return{rows:[],view:'all',err:''};},
  methods:{
    async load(v){ this.view=v||this.view; this.err=''; try{
      const r = await apiGet('/me/resident/services',{view:this.view});
      this.rows=(r.data&&r.data.data)||[];
    }catch(e){ this.err=(e.response&&e.response.data&&e.response.data.error&&e.response.data.error.message)||e.message; }},
    fmt(s){ return s? new Date(s.replace(' ','T')).toLocaleString():''; },
    status(r){ const now=Date.now(), st=new Date(r.start_time.replace(' ','T')).getTime(), en=new Date(r.end_time.replace(' ','T')).getTime(); if(en<now) return 'Completed'; if(st>now) return 'Upcoming'; return 'In progress'; },
    badge(r){ var s=this.status(r); var map={Upcoming:'#cff4fc', 'In progress':'#fff3cd', Completed:'#e2e3e5'}; var bg=map[s]||'#e2e3e5'; return '<span style="padding:2px 8px;border-radius:10px;background:'+bg+'">'+s+'</span>'; },
    btn(v){ return 'margin-right:8px;'+(this.view===v?'font-weight:bold;':''); }
  },
  mounted(){ this.load('all'); }
}).mount('#app');
</script>
<?php renderFooter(); ?>
