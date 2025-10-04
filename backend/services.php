<?php
require_once __DIR__ . '/includes/layout.php';
renderHeader('Services');
?>
<main style="max-width:1100px;margin:24px auto;padding:16px;">
  <div id="app">
    <div v-if="msg" style="background:#ecfdf3;border:1px solid #badbcc;padding:10px;border-radius:6px;margin-bottom:12px;">
      {{ msg }}
    </div>
    <div v-if="err" style="background:#fdecea;border:1px solid #f5c2c7;padding:10px;border-radius:6px;margin-bottom:12px;color:#b00020;">
      {{ err }}
    </div>

    <div v-if="loading">Loading…</div>
    <template v-else>
      <div v-if="!me || me.role !== 'ADMIN'">
        <p>Admins only. <a href="index.php">Back to home</a></p>
      </div>

      <div v-else>
        <h2 style="margin:0 0 12px;">Services</h2>

        <!-- Add new service -->
        <details style="margin: 12px 0;">
          <summary style="cursor:pointer;">Add New Service</summary>
          <form @submit.prevent="createSvc" style="display:grid;grid-template-columns:repeat(3,minmax(220px,1fr));gap:10px;margin-top:10px;">
            <div>
              <label>Name</label>
              <input v-model.trim="create.name" required>
            </div>
            <div>
              <label>Category</label>
              <select v-model.number="create.category_id" required>
                <option value="">-- Choose --</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label>Status</label>
              <select v-model="create.status">
                <option value="Inactive">Inactive</option>
                <option value="Active">Active</option>
                <option value="Scheduled">Scheduled</option>
              </select>
            </div>
            <div style="grid-column: 1 / -1;">
              <label>Description</label>
              <input v-model.trim="create.description">
            </div>
            <div>
              <label>Duration Min</label>
              <input v-model.number="create.duration_minutes_min" type="number" min="0" placeholder="e.g. 30">
            </div>
            <div>
              <label>Duration Max</label>
              <input v-model.number="create.duration_minutes_max" type="number" min="0" placeholder="e.g. 60">
            </div>
            <div>
              <label>Cost</label>
              <input v-model="create.cost" type="text" placeholder="e.g. 75.00">
            </div>
            <div style="grid-column: 1 / -1;">
              <label>Frequency</label>
              <input v-model.trim="create.frequency" placeholder="e.g. Weekly">
            </div>
            <div style="grid-column: 1 / -1;">
              <button type="submit" style="padding:8px 12px;border:none;border-radius:6px;background:#2b6cb0;color:#fff;cursor:pointer;">Add Service</button>
            </div>
          </form>
        </details>

        <!-- List + edit + schedule -->
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f8f8;border-bottom:1px solid #ddd;">
                <th style="text-align:left;padding:8px;">Name</th>
                <th style="text-align:left;padding:8px;">Category</th>
                <th style="text-align:left;padding:8px;">Status</th>
                <th style="text-align:left;padding:8px;">Cost</th>
                <th style="text-align:left;padding:8px;">Duration</th>
                <th style="text-align:left;padding:8px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in rows" :key="s.id" style="border-bottom:1px solid #eee;">
                <!-- view row -->
                <template v-if="editId !== s.id">
                  <td style="padding:8px;">{{ s.name }}</td>
                  <td style="padding:8px;">{{ s.category_name }}</td>
                  <td style="padding:8px;">{{ s.status }}</td>
                  <td style="padding:8px;">{{ s.cost || '' }}</td>
                  <td style="padding:8px;">{{ (s.duration_minutes_min||'') + (s.duration_minutes_max?('–'+s.duration_minutes_max):'') }}</td>
                  <td style="padding:8px;">
                    <button @click="beginEdit(s)" style="padding:6px 10px;border:none;border-radius:6px;background:#555;color:#fff;cursor:pointer;">Edit</button>
                    <button @click="beginSchedule(s)" style="padding:6px 10px;border:none;border-radius:6px;background:#0d6efd;color:#fff;cursor:pointer;margin-left:6px;">Schedule</button>
                    <button @click="doDelete(s)" style="padding:6px 10px;border:none;border-radius:6px;background:#dc3545;color:#fff;cursor:pointer;margin-left:6px;">Delete</button>
                  </td>
                </template>

                <!-- edit row -->
                <template v-else>
                  <td style="padding:8px;"><input v-model.trim="edit.name"></td>
                  <td style="padding:8px;">
                    <select v-model.number="edit.category_id">
                      <option v-for="c in categories" :key="'e'+c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                  </td>
                  <td style="padding:8px;">
                    <select v-model="edit.status">
                      <option value="Inactive">Inactive</option>
                      <option value="Active">Active</option>
                      <option value="Scheduled">Scheduled</option>
                    </select>
                  </td>
                  <td style="padding:8px;"><input v-model="edit.cost" placeholder="e.g. 75.00"></td>
                  <td style="padding:8px;">
                    <div style="display:flex;gap:6px;">
                      <input v-model.number="edit.duration_minutes_min" type="number" min="0" placeholder="Min">
                      <input v-model.number="edit.duration_minutes_max" type="number" min="0" placeholder="Max">
                    </div>
                  </td>
                  <td style="padding:8px;">
                    <div style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">
                      <button @click="saveEdit" style="padding:6px 10px;border:none;border-radius:6px;background:#2b6cb0;color:#fff;cursor:pointer;">Save</button>
                      <button @click="cancelEdit" style="padding:6px 10px;border:none;border-radius:6px;background:#6c757d;color:#fff;cursor:pointer;">Cancel</button>
                    </div>
                    <div>
                      <input v-model.trim="edit.description" placeholder="Description">
                      <input v-model.trim="edit.frequency" placeholder="Frequency" style="margin-top:6px;">
                    </div>
                  </td>
                </template>
              </tr>

              <!-- inline schedule panel -->
              <tr v-if="schedule.show" style="background:#f9fafb;">
                <td colspan="6" style="padding:12px;">
                  <strong>Schedule: {{ schedule.service_name }}</strong>
                  <form @submit.prevent="createSchedule" style="display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:10px;margin-top:10px;">
                    <div>
                      <label>Start date</label>
                      <input v-model="schedule.start_date" type="date" required>
                    </div>
                    <div>
                      <label>Start time</label>
                      <input v-model="schedule.start_time" type="time" required>
                    </div>
                    <div>
                      <label>End date</label>
                      <input v-model="schedule.end_date" type="date" required>
                    </div>
                    <div>
                      <label>End time</label>
                      <input v-model="schedule.end_time" type="time" required>
                    </div>
                    <div style="grid-column: 1 / -1;">
                      <label>Location</label>
                      <input v-model.trim="schedule.location" placeholder="e.g. Rehab Room A">
                    </div>
                    <div style="grid-column: 1 / -1;">
                      <label>Notes</label>
                      <input v-model.trim="schedule.notes" placeholder="Optional">
                    </div>

                    <!-- NEW: Staff selection -->
                    <div style="grid-column: 1 / -1;">
                      <label>Staff (hold Ctrl/Cmd for multiple)</label>
                      <select multiple size="6" v-model="schedule.staff_ids" style="width:100%;max-width:520px;">
                        <option v-for="st in staffActive" :key="st.id" :value="st.id">{{ st.full_name }} ({{ st.username }})</option>
                      </select>
                      <div style="opacity:.7;font-size:12px;margin-top:4px;">Selected: {{ schedule.staff_ids.length }}</div>
                    </div>

                    <div style="grid-column: 1 / -1; display:flex; gap:8px;">
                      <button type="submit" style="padding:8px 12px;border:none;border-radius:6px;background:#0d6efd;color:#fff;cursor:pointer;">Create Session</button>
                      <button @click.prevent="cancelSchedule" style="padding:8px 12px;border:none;border-radius:6px;background:#6c757d;color:#fff;cursor:pointer;">Cancel</button>
                    </div>
                  </form>
                </td>
              </tr>

            </tbody>
          </table>
        </div>

      </div>
    </template>
  </div>
</main>

<script src="https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.7/dist/axios.min.js"></script>
<script>
  var APP_BASE = "<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); ?>";
  var API_BASE = APP_BASE + "/api";
  const api = axios.create({ baseURL: API_BASE, withCredentials: true });

  function combine(dt, tm){ if(!dt||!tm) return null; return dt + ' ' + (tm.length===5?tm+':00':tm); }

  Vue.createApp({
    data(){
      return {
        loading: true,
        me: null,
        categories: [],
        rows: [],
        staff: [],                 // NEW: all staff rows
        err: '',
        msg: '',
        editId: 0,
        edit: { id:0,name:'',category_id:'',status:'Inactive',cost:'',duration_minutes_min:null,duration_minutes_max:null,description:'',frequency:'' },
        create: { name:'', category_id:'', status:'Inactive', description:'', duration_minutes_min:null, duration_minutes_max:null, frequency:'', cost:'' },
        schedule: {
          show:false, service_id:0, service_name:'',
          start_date:'', start_time:'', end_date:'', end_time:'',
          location:'', notes:'',
          staff_ids: []            // NEW: selected staff IDs
        }
      };
    },
    computed:{
      staffActive(){ return this.staff.filter(s => (s.is_active|0) === 1); }
    },
    async mounted(){
      try {
        await this.ensureCsrf();
        await this.loadMe();
        if (!this.me || this.me.role !== 'ADMIN') { this.loading=false; return; }
        await Promise.all([ this.loadCategories(), this.loadServices(), this.loadStaff() ]);
      } catch (e) {
        this.err = this.pickError(e);
      } finally {
        this.loading = false;
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
        const csrf = r.data && r.data.data ? r.data.data.csrf : '';
        if (csrf) api.defaults.headers.common['X-CSRF-Token'] = csrf;
      },
      async loadMe(){
        try {
          const r = await api.get('/index.php', { params:{ r:'/v1/me', __ts:Date.now() } });
          this.me = (r.data && r.data.data) ? r.data.data.user : null;
        } catch(e) { this.me = null; }
      },
      async loadCategories(){
        const r = await api.get('/index.php', { params:{ r:'/v1/categories', __ts:Date.now() } });
        this.categories = (r.data && r.data.data) ? r.data.data : [];
      },
      async loadServices(){
        const r = await api.get('/index.php', { params:{ r:'/v1/services', __ts:Date.now() } });
        this.rows = (r.data && r.data.data) ? r.data.data : [];
      },
      async loadStaff(){
        const r = await api.get('/index.php', { params:{ r:'/v1/staff', __ts:Date.now() } });
        this.staff = (r.data && r.data.data) ? r.data.data : [];
      },

      beginEdit(s){
        this.msg=''; this.err=''; this.editId = s.id;
        this.edit = {
          id: s.id,
          name: s.name || '',
          category_id: s.category_id || '',
          status: s.status || 'Inactive',
          cost: s.cost || '',
          duration_minutes_min: s.duration_minutes_min || null,
          duration_minutes_max: s.duration_minutes_max || null,
          description: s.description || '',
          frequency: s.frequency || ''
        };
        this.schedule.show = false;
      },
      cancelEdit(){
        this.editId = 0;
        this.edit = { id:0,name:'',category_id:'',status:'Inactive',cost:'',duration_minutes_min:null,duration_minutes_max:null,description:'',frequency:'' };
      },
      async saveEdit(){
        try{
          const id = this.edit.id; if (!id) return;
          const payload = {
            name: this.edit.name,
            category_id: this.edit.category_id|0,
            status: this.edit.status,
            cost: this.edit.cost!=='' ? this.edit.cost : null,
            duration_minutes_min: this.edit.duration_minutes_min!==null && this.edit.duration_minutes_min!=='' ? (this.edit.duration_minutes_min|0) : null,
            duration_minutes_max: this.edit.duration_minutes_max!==null && this.edit.duration_minutes_max!=='' ? (this.edit.duration_minutes_max|0) : null,
            description: this.edit.description || null,
            frequency: this.edit.frequency || null
          };
          await api.put('/index.php', payload, { params:{ r:'/v1/services/'+id } });
          this.msg = 'Saved';
          this.cancelEdit();
          await this.loadServices();
        } catch (e) { this.err = this.pickError(e); }
      },
      async createSvc(){
        try {
          const payload = {
            name: this.create.name,
            category_id: this.create.category_id|0,
            status: this.create.status,
            description: this.create.description || null,
            duration_minutes_min: this.create.duration_minutes_min!==null && this.create.duration_minutes_min!=='' ? (this.create.duration_minutes_min|0) : null,
            duration_minutes_max: this.create.duration_minutes_max!==null && this.create.duration_minutes_max!=='' ? (this.create.duration_minutes_max|0) : null,
            frequency: this.create.frequency || null,
            cost: this.create.cost!=='' ? this.create.cost : null
          };
          await api.post('/index.php', payload, { params:{ r:'/v1/services' } });
          this.msg = 'Service created';
          this.create = { name:'', category_id:'', status:'Inactive', description:'', duration_minutes_min:null, duration_minutes_max:null, frequency:'', cost:'' };
          await this.loadServices();
        } catch (e) { this.err = this.pickError(e); }
      },
      async doDelete(s){
        if (!confirm('Delete service "' + (s.name) + '"?')) return;
        try {
          await api.delete('/index.php', { params:{ r:'/v1/services/'+s.id } });
          this.msg = 'Deleted';
          await this.loadServices();
        } catch (e) { this.err = this.pickError(e); }
      },

      /* ----------- SCHEDULE UI ----------- */
      beginSchedule(s){
        this.err=''; this.msg='';
        this.editId = 0; // hide edit row if open
        this.schedule = {
          show: true,
          service_id: s.id,
          service_name: s.name,
          start_date: '', start_time: '',
          end_date: '', end_time: '',
          location: '', notes: '',
          staff_ids: [] // reset selection
        };
      },
      cancelSchedule(){
        this.schedule.show = false;
        this.schedule = { show:false, service_id:0, service_name:'', start_date:'', start_time:'', end_date:'', end_time:'', location:'', notes:'', staff_ids:[] };
      },
      async createSchedule(){
        try {
          const start = combine(this.schedule.start_date, this.schedule.start_time);
          const end   = combine(this.schedule.end_date,   this.schedule.end_time);
          if (!start || !end) { this.err='Please fill start/end date & time.'; return; }
          if (new Date(start) >= new Date(end)) { this.err='End must be after start.'; return; }

          // 1) Create the session
          const payload = {
            service_id: this.schedule.service_id|0,
            start_time: start,
            end_time:   end,
            location:   this.schedule.location || null,
            notes:      this.schedule.notes || null
          };
          const r = await api.post('/index.php', payload, { params:{ r:'/v1/service-schedule' } });
          const newId = (r.data && r.data.data && r.data.data.id) ? r.data.data.id : null;

          // 2) Assign staff (if any selected). Ignore errors but report.
          if (newId && this.schedule.staff_ids.length > 0) {
            try {
              await api.post('/index.php', {
                schedule_id: newId,
                staff_user_ids: this.schedule.staff_ids.map(function(x){ return x|0; })
              }, { params:{ r:'/v1/staff-assignments' } });
              this.msg = 'Session created and staff assigned.';
            } catch (e2) {
              this.msg = 'Session created, but staff assignment failed: ' + this.pickError(e2);
            }
          } else {
            this.msg = 'Session created.';
          }

          this.cancelSchedule();
        } catch (e) {
          this.err = this.pickError(e);
        }
      }
    }
  }).mount('#app');
</script>
<?php renderFooter(); ?>
