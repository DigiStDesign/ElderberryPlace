<?php
require_once __DIR__ . '/includes/layout.php';
renderHeader('All Scheduled Sessions');
?>
<main style="max-width:1200px;margin:24px auto;padding:16px;">
  <div id="app">
    <div v-if="msg" style="background:#ecfdf3;border:1px solid #badbcc;padding:10px;border-radius:6px;margin-bottom:12px;">{{ msg }}</div>
    <div v-if="err" style="background:#fdecea;border:1px solid #f5c2c7;padding:10px;border-radius:6px;margin-bottom:12px;color:#b00020;">{{ err }}</div>

    <section style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:12px;">
      <div>
        <label>Service<br>
          <select v-model.number="filters.service_id" style="min-width:240px">
            <option :value="0">All services</option>
            <option v-for="svc in services" :key="svc.id" :value="svc.id">{{ svc.name }}</option>
          </select>
        </label>
      </div>
      <div><label>From<br><input type="date" v-model="filters.from"></label></div>
      <div><label>To<br><input type="date" v-model="filters.to"></label></div>
      <div><button @click="loadSessions" style="padding:8px 12px;border:none;border-radius:6px;background:#2b6cb0;color:#fff;cursor:pointer;">Load</button></div>
      <div style="margin-left:auto;opacity:.8">Role: {{ me ? me.role : 'GUEST' }}</div>
    </section>

    <div v-if="loading">Loading…</div>
    <div v-else>
      <div v-if="rows.length===0" style="background:#eef2ff;border:1px solid #c7d2fe;padding:12px;border-radius:8px;">No sessions found for this filter.</div>
      <div v-else style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="background:#f8f8f8;border-bottom:1px solid #ddd;">
              <th style="text-align:left;padding:8px;">Service</th>
              <th style="text-align:left;padding:8px;">Staff</th>
              <th style="text-align:left;padding:8px;">Residents</th>
              <th style="text-align:left;padding:8px;">Start</th>
              <th style="text-align:left;padding:8px;">End</th>
              <th style="text-align:left;padding:8px;">Location</th>
              <th style="text-align:left;padding:8px;">Notes</th>
              <th style="text-align:left;padding:8px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="r.id" style="border-bottom:1px solid #eee;">
              <td style="padding:8px;">{{ r.service_name }}</td>
              <td style="padding:8px;">{{ r.staff_names ? r.staff_names : '(unassigned)' }}</td>
              <td style="padding:8px;">{{ r.resident_names ? r.resident_names : '(unassigned)' }}</td>
              <td style="padding:8px;">{{ r.start_time }}</td>
              <td style="padding:8px;">{{ r.end_time }}</td>
              <td style="padding:8px;">{{ r.location || '' }}</td>
              <td style="padding:8px;white-space:pre-line;">{{ r.notes || '' }}</td>
              <td style="padding:8px;">
                <!-- Resident actions -->
                <template v-if="me && me.role==='RESIDENT'">
                  <button v-if="!r.booked_by_me"
                          @click="bookMe(r)"
                          style="padding:6px 10px;border:none;border-radius:6px;background:#0d6efd;color:#fff;cursor:pointer;">
                    Book Me
                  </button>
                  <button v-else
                          @click="cancelMyBooking(r)"
                          style="padding:6px 10px;border:none;border-radius:6px;background:#dc3545;color:#fff;cursor:pointer;">
                    Cancel My Booking
                  </button>
                </template>

                <!-- Admin/Staff actions -->
                <template v-if="me && (me.role==='ADMIN' || me.role==='STAFF')">
                  <button @click="openAssignStaff(r)"
                          style="padding:6px 10px;border:none;border-radius:6px;background:#198754;color:#fff;cursor:pointer;margin-left:6px;">
                    Assign Staff
                  </button>
                  <button @click="openAssignResidents(r)"
                          style="padding:6px 10px;border:none;border-radius:6px;background:#6f42c1;color:#fff;cursor:pointer;margin-left:6px;">
                    Assign Residents
                  </button>
                  <button @click="cancelSession(r)"
                          style="padding:6px 10px;border:none;border-radius:6px;background:#6c757d;color:#fff;cursor:pointer;margin-left:6px;">
                    Cancel Session
                  </button>
                </template>
              </td>
            </tr>

            <!-- inline assignment row -->
            <tr v-if="panel.show" style="background:#f9fafb;">
              <td colspan="8" style="padding:12px;">
                <div style="display:flex;gap:24px;flex-wrap:wrap;">
                  <div v-if="panel.mode==='staff'" style="min-width:280px;max-width:520px;flex:1;">
                    <h4 style="margin:0 0 8px;">Assign Staff — {{ panel.service_name }} @ {{ panel.start_time }}</h4>
                    <div style="margin-bottom:8px;">
                      <select multiple size="8" v-model="panel.staff_ids" style="width:100%;">
                        <option v-for="s in staffActive" :key="s.id" :value="s.id">{{ s.full_name }} ({{ s.username }})</option>
                      </select>
                      <div style="opacity:.7;font-size:12px;margin-top:4px;">Selected: {{ panel.staff_ids.length }}</div>
                    </div>
                    <div style="display:flex;gap:8px;">
                      <button @click="assignStaff" style="padding:8px 12px;border:none;border-radius:6px;background:#198754;color:#fff;cursor:pointer;">Save</button>
                      <button @click="closePanel" style="padding:8px 12px;border:none;border-radius:6px;background:#6c757d;color:#fff;cursor:pointer;">Close</button>
                    </div>
                  </div>

                  <div v-if="panel.mode==='residents'" style="min-width:280px;max-width:520px;flex:1;">
                    <h4 style="margin:0 0 8px;">Assign Residents — {{ panel.service_name }} @ {{ panel.start_time }}</h4>
                    <div style="margin-bottom:8px;">
                      <select multiple size="8" v-model="panel.resident_ids" style="width:100%;">
                        <option v-for="r in residentsActive" :key="r.id" :value="r.id">{{ r.full_name }} ({{ r.username }})</option>
                      </select>
                      <div style="opacity:.7;font-size:12px;margin-top:4px;">Selected: {{ panel.resident_ids.length }}</div>
                    </div>
                    <div style="display:flex;gap:8px;">
                      <button @click="assignResidents" style="padding:8px 12px;border:none;border-radius:6px;background:#6f42c1;color:#fff;cursor:pointer;">Save</button>
                      <button @click="closePanel" style="padding:8px 12px;border:none;border-radius:6px;background:#6c757d;color:#fff;cursor:pointer;">Close</button>
                    </div>
                  </div>
                </div>
              </td>
            </tr>

          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<script src="https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.7/dist/axios.min.js"></script>
<script>
  var APP_BASE = "<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\\\'); ?>";
  var API_BASE = APP_BASE + "/api";
  const api = axios.create({ baseURL: API_BASE, withCredentials: true });

  function todayStr(){ var d=new Date(), m=('0'+(d.getMonth()+1)).slice(-2), dd=('0'+d.getDate()).slice(-2); return d.getFullYear()+'-'+m+'-'+dd; }
  function plusDaysStr(days){ var d=new Date(); d.setDate(d.getDate()+days); var m=('0'+(d.getMonth()+1)).slice(-2), dd=('0'+d.getDate()).slice(-2); return d.getFullYear()+'-'+m+'-'+dd; }

  Vue.createApp({
    data(){
      return {
        loading:true,
        me:null,
        services:[],
        rows:[],
        staff:[],
        residents:[],
        err:'',
        msg:'',
        filters:{ service_id:0, from: todayStr(), to: plusDaysStr(30) },
        panel: { show:false, mode:'', schedule_id:0, service_name:'', start_time:'', staff_ids:[], resident_ids:[] }
      };
    },
    computed:{
      staffActive(){ return this.staff.filter(function(s){ return (s.is_active|0)===1; }); },
      residentsActive(){ return this.residents.filter(function(r){ return (r.is_active|0)===1; }); }
    },
    async mounted(){
      try{
        await this.ensureCsrf();
        await this.loadMe();
        await this.loadServices();
        await this.loadSessions();
      }catch(e){
        this.err = this.pickError(e);
      }finally{
        this.loading=false;
      }
    },
    methods:{
      pickError(e){
        return (e && e.response && e.response.data && e.response.data.error && e.response.data.error.message)
          ? e.response.data.error.message
          : (e && e.message ? e.message : 'Unexpected error');
      },
      async ensureCsrf(){
        const r = await api.get('/index.php', { params:{ r:'/v1/csrf', __ts:Date.now() } });
        const token = r.data && r.data.data ? r.data.data.csrf : '';
        if (token) api.defaults.headers.common['X-CSRF-Token'] = token;
      },
      async loadMe(){
        try{
          const r = await api.get('/index.php', { params:{ r:'/v1/me', __ts:Date.now() } });
          this.me = (r.data && r.data.data) ? r.data.data.user : null;
        }catch(e){ this.me=null; }
      },
      async loadServices(){
        const r = await api.get('/index.php', { params:{ r:'/v1/services', __ts:Date.now() } });
        this.services = (r.data && r.data.data) ? r.data.data : [];
      },
      async loadSessions(){
        this.err=''; this.msg='';
        const params = { r:'/v1/service-schedule/summary', __ts:Date.now() };
        if (this.filters.service_id) params.service_id = this.filters.service_id;
        if (this.filters.from)       params.from       = this.filters.from;
        if (this.filters.to)         params.to         = this.filters.to;
        const r = await api.get('/index.php', { params });
        this.rows = (r.data && r.data.data) ? r.data.data : [];
      },

      /* Resident self-book/unbook */
      async bookMe(row){
        try{
          await api.post('/index.php', { schedule_id: row.id }, { params:{ r:'/v1/me/resident/book' } });
          this.msg = 'Booked into session #' + row.id;
          await this.loadSessions();
        }catch(e){ this.err = this.pickError(e); }
      },
      async cancelMyBooking(row){
        if (!confirm('Cancel your booking for this session?')) return;
        try{
          await api.post('/index.php', { schedule_id: row.id }, { params:{ r:'/v1/me/resident/unbook' } });
          this.msg = 'Your booking was cancelled.';
          await this.loadSessions();
        }catch(e){ this.err = this.pickError(e); }
      },

      /* Admin/Staff assignment & cancel session */
      async openAssignStaff(row){
        try{
          if (!this.staff.length){
            const r = await api.get('/index.php', { params:{ r:'/v1/staff', __ts:Date.now() } });
            this.staff = (r.data && r.data.data) ? r.data.data : [];
          }
          this.panel = { show:true, mode:'staff', schedule_id: row.id, service_name:row.service_name, start_time:row.start_time, staff_ids:[], resident_ids:[] };
        }catch(e){ this.err = this.pickError(e); }
      },
      async openAssignResidents(row){
        try{
          if (!this.residents.length){
            const r = await api.get('/index.php', { params:{ r:'/v1/residents', __ts:Date.now() } });
            this.residents = (r.data && r.data.data && r.data.data.items) ? r.data.data.items : [];
          }
          this.panel = { show:true, mode:'residents', schedule_id: row.id, service_name:row.service_name, start_time:row.start_time, staff_ids:[], resident_ids:[] };
        }catch(e){ this.err = this.pickError(e); }
      },
      closePanel(){ this.panel = { show:false, mode:'', schedule_id:0, service_name:'', start_time:'', staff_ids:[], resident_ids:[] }; },
      async assignStaff(){
        try{
          if (!this.panel.schedule_id || this.panel.staff_ids.length===0){ this.err='Pick at least one staff.'; return; }
          await api.post('/index.php', {
            schedule_id: this.panel.schedule_id,
            staff_user_ids: this.panel.staff_ids.map(function(x){ return x|0; })
          }, { params:{ r:'/v1/staff-assignments' } });
          this.msg = 'Staff assigned.';
          this.closePanel();
          await this.loadSessions();
        }catch(e){ this.err = this.pickError(e); }
      },
      async assignResidents(){
        try{
          if (!this.panel.schedule_id || this.panel.resident_ids.length===0){ this.err='Pick at least one resident.'; return; }
          await api.post('/index.php', {
            schedule_id: this.panel.schedule_id,
            resident_user_ids: this.panel.resident_ids.map(function(x){ return x|0; })
          }, { params:{ r:'/v1/resident-bookings' } });
          this.msg = 'Residents assigned.';
          this.closePanel();
          await this.loadSessions();
        }catch(e){ this.err = this.pickError(e); }
      },
      async cancelSession(row){
        if (!confirm('Cancel this entire session? This removes all staff/resident assignments.')) return;
        try{
          await api.delete('/index.php', { params:{ r:'/v1/service-schedule/'+row.id } });
          this.msg = 'Session cancelled.';
          await this.loadSessions();
        }catch(e){ this.err = this.pickError(e); }
      }
    }
  }).mount('#app');
</script>
<?php renderFooter(); ?>
