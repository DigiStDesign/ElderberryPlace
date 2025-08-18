<?php require_once __DIR__ . '/includes/layout.php'; renderHeader('Service Scheduling'); ?>
<main style="max-width:1100px;margin:20px auto;">
  <div id="app">
    <h2>Schedule Services</h2>
    <div v-if="err" style="color:#b00020">{{err}}</div>

    <div style="display:flex;gap:20px;align-items:flex-start;">
      <!-- Left: pick service + list sessions -->
      <div style="flex:1;">
        <h3>Sessions</h3>
        <label>Service
          <select v-model.number="service_id" @change="loadSessions">
            <option value="">-- Select --</option>
            <option v-for="s in services" :value="s.id">{{ s.name }}</option>
          </select>
        </label>

        <table v-if="sessions.length" border="1" cellpadding="6" cellspacing="0" style="width:100%;margin-top:10px;border-collapse:collapse;">
          <thead><tr><th>Start</th><th>End</th><th>Location</th><th>Notes</th></tr></thead>
          <tbody>
            <tr v-for="ss in sessions">
              <td>{{ ss.start_time }}</td><td>{{ ss.end_time }}</td><td>{{ ss.location }}</td><td>{{ ss.notes }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Right: create session, assign staff/residents -->
      <div style="flex:0 0 380px;">
        <h3>Create Session</h3>
        <form @submit.prevent="createSession" style="display:grid;gap:8px;">
          <select v-model.number="service_id" required>
            <option value="">-- Service --</option>
            <option v-for="s in services" :value="s.id">{{ s.name }}</option>
          </select>
          <input v-model="start_time" type="datetime-local" required />
          <input v-model="end_time" type="datetime-local" required />
          <input v-model="location" placeholder="Location (optional)" />
          <input v-model="notes" placeholder="Notes (optional)" />
          <button type="submit">Add</button>
        </form>

        <hr>

        <h3>Assign Staff</h3>
        <form @submit.prevent="assignStaff" style="display:grid;gap:8px;">
          <input v-model.number="schedule_id" placeholder="Schedule ID" required />
          <input v-model.number="staff_user_id" placeholder="Staff user ID" required />
          <button type="submit">Assign</button>
        </form>

        <h3>Assign Residents</h3>
        <form @submit.prevent="assignResidents" style="display:grid;gap:8px;">
          <input v-model.number="schedule_id" placeholder="Schedule ID" required />
          <input v-model="resident_ids" placeholder="Resident IDs (comma-separated)" />
          <button type="submit">Assign</button>
        </form>
      </div>
    </div>
  </div>
</main>
<script src="https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.7/dist/axios.min.js"></script>
<script src="/assets/app.js"></script>
<script>
Vue.createApp({
  data(){return{
    services:[], service_id:'', sessions:[], err:'',
    start_time:'', end_time:'', location:'', notes:'',
    schedule_id:'', staff_user_id:'', resident_ids:''
  }},
  methods:{
    async loadServices(){ const r=await apiGet('/services'); this.services=(r.data&&r.data.data&&r.data.data.services)||[]; },
    async loadSessions(){ if(!this.service_id){this.sessions=[];return;} const r=await apiGet('/service-schedule',{service_id:this.service_id}); this.sessions=(r.data&&r.data.data&&r.data.data.sessions)||[]; },
    async createSession(){
      try{
        await apiPost('/service-schedule',{service_id:this.service_id,start_time:this.start_time.replace('T',' ')+':00',end_time:this.end_time.replace('T',' ')+':00',location:this.location||null,notes:this.notes||null});
        this.location=''; this.notes=''; this.loadSessions();
      }catch(e){ this.err=this.msg(e); }
    },
    async assignStaff(){ try{ await apiPost('/staff-assignments',{schedule_id:this.schedule_id,staff_user_id:this.staff_user_id}); alert('Staff assigned'); }catch(e){ this.err=this.msg(e); } },
    async assignResidents(){
      try{
        var ids = this.resident_ids.split(',').map(function(s){return parseInt(s,10)}).filter(function(n){return !isNaN(n);});
        await apiPost('/resident-bookings',{schedule_id:this.schedule_id,resident_user_ids:ids});
        alert('Residents assigned');
      }catch(e){ this.err=this.msg(e); }
    },
    msg(e){ return (e.response&&e.response.data&&e.response.data.error&&e.response.data.error.message)||e.message; }
  },
  async mounted(){ await this.loadServices(); }
}).mount('#app');
</script>
<?php renderFooter(); ?>
