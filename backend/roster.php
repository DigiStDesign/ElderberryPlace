<?php require_once __DIR__ . '/includes/layout.php'; renderHeader('My Roster'); ?>
<main style="max-width:900px;margin:20px auto;">
  <div id="app">
    <h2>My Roster</h2>
    <div v-if="err" style="color:#b00020">{{err}}</div>
    <div v-else-if="rows.length===0">No bookings assigned to you (yet).</div>
    <table v-else border="0" cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse;">
      <thead><tr style="background:#f2f2f2;border-bottom:1px solid #ddd;"><th>Start</th><th>End</th><th>Service</th><th>Resident</th><th>Location</th></tr></thead>
      <tbody>
        <tr v-for="r in rows" style="border-bottom:1px solid #eee;">
          <td>{{ r.start_time }}</td><td>{{ r.end_time }}</td><td>{{ r.service_name }}</td><td>{{ r.resident_name || '(unassigned)' }}</td><td>{{ r.location }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</main>
<script src="https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.7/dist/axios.min.js"></script>
<script src="/assets/app.js"></script>
<script>
Vue.createApp({
  data(){return{rows:[],err:''};},
  async mounted(){
    try{ const r=await apiGet('/me/roster'); this.rows=(r.data&&r.data.data)||[]; }
    catch(e){ this.err=(e.response&&e.response.data&&e.response.data.error&&e.response.data.error.message)||e.message; }
  }
}).mount('#app');
</script>
<?php renderFooter(); ?>
