<?php
// TEMP: show PHP errors so the page doesn't render blank if there's a fatal.
// Remove these two lines once the page loads fine.
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/layout.php';
// layout.php already requires includes/auth.php, which exposes start_secure_session/current_user
start_secure_session();
$phpUser = current_user(); // same session as API
renderHeader('Manage Staff');
?>
<main style="max-width: 1100px; margin: 24px auto; padding: 16px;">

  <!-- Pre-hydrate current user from PHP session -->
  <script>
    window.__ME = <?php
      echo json_encode($phpUser ? array(
        'id'        => (int)$phpUser['id'],
        'username'  => $phpUser['username'],
        'full_name' => $phpUser['full_name'],
        'role'      => $phpUser['role']
      ) : null);
    ?>;
    console.log('PHP prehydrated user:', window.__ME);
  </script>

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
        <h2 style="margin:0 0 12px;">Staff</h2>

        <details style="margin: 12px 0;">
          <summary style="cursor:pointer;">Add New Staff</summary>
          <form @submit.prevent="createStaff" style="display:grid;grid-template-columns:repeat(3,minmax(220px,1fr));gap:10px;margin-top:10px;">
            <div><label>Full name</label><input v-model.trim="create.full_name" required></div>
            <div><label>Username</label><input v-model.trim="create.username" required></div>
            <div><label>Email (optional)</label><input v-model.trim="create.email" type="email"></div>
            <div><label>Temp password</label><input v-model="create.password" required></div>
            <div>
              <label>Status</label>
              <select v-model.number="create.is_active">
                <option :value="1">Active</option>
                <option :value="0">Inactive</option>
              </select>
            </div>
            <div>
              <label>Job role</label>
              <select v-model.number="create.staff_job_id" required>
                <option value="">-- Choose --</option>
                <option v-for="j in jobs" :key="j.id" :value="j.id">{{ j.name }}</option>
              </select>
            </div>
            <div><label>Started on</label><input v-model="create.started_on" type="date"></div>
            <div style="grid-column: span 2;"><label>Notes</label><input v-model.trim="create.notes"></div>
            <div style="grid-column: 1 / -1; margin-top:6px;">
              <button type="submit" style="padding:8px 12px;border:none;border-radius:6px;background:#2b6cb0;color:#fff;cursor:pointer;">Add Staff</button>
            </div>
          </form>
        </details>

        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f8f8;border-bottom:1px solid #ddd;">
                <th style="text-align:left;padding:8px;">Name</th>
                <th style="text-align:left;padding:8px;">Username</th>
                <th style="text-align:left;padding:8px;">Email</th>
                <th style="text-align:left;padding:8px;">Job</th>
                <th style="text-align:left;padding:8px;">Started</th>
                <th style="text-align:left;padding:8px;">Status</th>
                <th style="text-align:left;padding:8px;">Notes</th>
                <th style="text-align:left;padding:8px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in staff" :key="s.id" style="border-bottom:1px solid #eee;">
                <template v-if="editId !== s.id">
                  <td style="padding:8px;">{{ s.full_name }}</td>
                  <td style="padding:8px;">{{ s.username }}</td>
                  <td style="padding:8px;">{{ s.email || '' }}</td>
                  <td style="padding:8px;">{{ s.job_name || '(unset)' }}</td>
                  <td style="padding:8px;">{{ s.started_on || '' }}</td>
                  <td style="padding:8px;">{{ s.is_active ? 'Active' : 'Inactive' }}</td>
                  <td style="padding:8px;white-space:pre-line;">{{ s.notes || '' }}</td>
                  <td style="padding:8px;">
                    <button @click="beginEdit(s)" style="padding:6px 10px;border:none;border-radius:6px;background:#555;color:#fff;cursor:pointer;">Edit</button>
                    <button @click="doDelete(s)" style="padding:6px 10px;border:none;border-radius:6px;background:#dc3545;color:#fff;cursor:pointer;margin-left:6px;">Delete</button>
                  </td>
                </template>
                <template v-else>
                  <td style="padding:8px;"><input v-model.trim="edit.full_name"></td>
                  <td style="padding:8px;"><input v-model.trim="edit.username"></td>
                  <td style="padding:8px;"><input v-model.trim="edit.email"></td>
                  <td style="padding:8px;">
                    <select v-model.number="edit.staff_job_id">
                      <option value="">-- Choose --</option>
                      <option v-for="j in jobs" :key="'e'+j.id" :value="j.id">{{ j.name }}</option>
                    </select>
                  </td>
                  <td style="padding:8px;"><input v-model="edit.started_on" type="date"></td>
                  <td style="padding:8px;">
                    <select v-model.number="edit.is_active">
                      <option :value="1">Active</option>
                      <option :value="0">Inactive</option>
                    </select>
                  </td>
                  <td style="padding:8px;"><input v-model.trim="edit.notes"></td>
                  <td style="padding:8px;">
                    <div style="display:flex;gap:6px;align-items:center;">
                      <button @click="saveEdit" style="padding:6px 10px;border:none;border-radius:6px;background:#2b6cb0;color:#fff;cursor:pointer;">Save</button>
                      <button @click="cancelEdit" style="padding:6px 10px;border:none;border-radius:6px;background:#6c757d;color:#fff;cursor:pointer;">Cancel</button>
                    </div>
                    <div style="margin-top:6px;">
                      <input v-model="edit.reset_password" placeholder="New password (optional)">
                    </div>
                  </td>
                </template>
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

  function toInt(v){ return v === '' || v === null || typeof v === 'undefined' ? null : (v|0); }

  Vue.createApp({
    data(){
      return {
        loading: true,
        me: window.__ME || null,
        jobs: [],
        staff: [],
        err: '',
        msg: '',
        editId: 0,
        edit: { id:0, username:'', full_name:'', email:'', is_active:1, staff_job_id:null, started_on:'', notes:'', reset_password:'' },
        create: { username:'', full_name:'', email:'', password:'', is_active:1, staff_job_id:null, started_on:'', notes:'' }
      };
    },
    async mounted(){
      try {
        await this.ensureCsrf();
        if (!this.me) { await this.loadMe(); }
        console.log('me after load:', this.me);
        if (!this.me || this.me.role !== 'ADMIN') { this.loading=false; return; }
        await this.loadJobs();
        await this.loadStaff();
      } catch (e) {
        console.error('mount error', e);
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
        console.log('csrf set?', !!csrf);
      },
      async loadMe(){
        const r = await api.get('/index.php', { params:{ r:'/v1/me', __ts:Date.now() } });
        this.me = (r.data && r.data.data) ? r.data.data.user : null;
      },
      async loadJobs(){
        const r = await api.get('/index.php', { params:{ r:'/v1/staff/jobs', __ts:Date.now() } });
        this.jobs = (r.data && r.data.data) ? r.data.data : [];
      },
      async loadStaff(){
        const r = await api.get('/index.php', { params:{ r:'/v1/staff', __ts:Date.now() } });
        this.staff = (r.data && r.data.data) ? r.data.data : [];
      },
      async createStaff(){
        try {
          this.err=''; this.msg='';
          const payload = {
            username: this.create.username,
            full_name: this.create.full_name,
            email: this.create.email || null,
            password: this.create.password,
            is_active: this.create.is_active|0,
            staff_job_id: toInt(this.create.staff_job_id),
            started_on: this.create.started_on || null,
            notes: this.create.notes || null
          };
          await api.post('/index.php', payload, { params:{ r:'/v1/staff' } });
          this.msg = 'Staff created.';
          this.create = { username:'', full_name:'', email:'', password:'', is_active:1, staff_job_id:null, started_on:'', notes:'' };
          await this.loadStaff();
        } catch (e) {
          console.error('create error', e);
          this.err = this.pickError(e);
        }
      },
      beginEdit(s){
        this.err=''; this.msg='';
        this.editId = s.id;
        this.edit = {
          id: s.id,
          username: s.username || '',
          full_name: s.full_name || '',
          email: s.email || '',
          is_active: (s.is_active ? 1 : 0),
          staff_job_id: toInt(s.staff_job_id),
          started_on: s.started_on || '',
          notes: s.notes || '',
          reset_password: ''
        };
      },
      cancelEdit(){
        this.editId = 0;
        this.edit = { id:0, username:'', full_name:'', email:'', is_active:1, staff_job_id:null, started_on:'', notes:'', reset_password:'' };
      },
      async saveEdit(){
        try {
          const id = this.edit.id;
          if (!id) return;
          const payload = {
            username: this.edit.username,
            full_name: this.edit.full_name,
            email: this.edit.email || null,
            is_active: this.edit.is_active|0,
            staff_job_id: toInt(this.edit.staff_job_id),
            started_on: this.edit.started_on || null,
            notes: this.edit.notes || null
          };
          if (this.edit.reset_password) payload.password = this.edit.reset_password;
          await api.put('/index.php', payload, { params:{ r:'/v1/staff/' + id } });
          this.msg = 'Saved.';
          this.cancelEdit();
          await this.loadStaff();
        } catch (e) {
          console.error('save error', e);
          this.err = this.pickError(e);
        }
      },
      async doDelete(s){
        if (!confirm('Delete staff "' + (s.full_name||s.username) + '"?')) return;
        try {
          this.err=''; this.msg='';
          await api.delete('/index.php', { params:{ r:'/v1/staff/' + s.id } });
          this.msg = 'Deleted.';
          await this.loadStaff();
        } catch (e) {
          console.error('delete error', e);
          this.err = this.pickError(e);
        }
      }
    }
  }).mount('#app');
</script>
<?php renderFooter(); ?>
