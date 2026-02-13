<?php
require_once __DIR__ . '/includes/layout.php';
renderHeader('Manage Visitors');
?>
<main style="max-width: 1100px; margin: 24px auto; padding: 16px;">
  <div id="app">
    <!-- messages -->
    <div v-if="msg" style="background:#ecfdf3;border:1px solid #badbcc;padding:10px;border-radius:6px;margin-bottom:12px;">
      {{ msg }}
    </div>
    <div v-if="err" style="background:#fdecea;border:1px solid #f5c2c7;padding:10px;border-radius:6px;margin-bottom:12px;color:#b00020;">
      {{ err }}
    </div>

    <!-- gate -->
    <div v-if="loading">Loading…</div>
    <template v-else>
      <div v-if="!me || me.role !== 'ADMIN'">
        <p>Admins only. <a href="index.php">Back to home</a></p>
      </div>

      <div v-else>
        <h2 style="margin:0 0 12px;">Visitors</h2>

        <!-- Add new visitor -->
        <details style="margin:12px 0;">
          <summary style="cursor:pointer;">Add New Visitor</summary>
          <form @submit.prevent="createVisitor" style="display:grid;grid-template-columns:repeat(3,minmax(220px,1fr));gap:10px;margin-top:10px;">
            <div>
              <label>Full name</label>
              <input v-model.trim="create.full_name" required>
            </div>
            <div>
              <label>Username</label>
              <input v-model.trim="create.username" required>
            </div>
            <div>
              <label>Email (optional)</label>
              <input v-model.trim="create.email" type="email">
            </div>

            <div>
              <label>Temp password</label>
              <input v-model="create.password" required>
            </div>
            <div>
              <label>Status</label>
              <select v-model.number="create.is_active">
                <option :value="1">Active</option>
                <option :value="0">Inactive</option>
              </select>
            </div>
            <div>
              <label>Phone (optional)</label>
              <input v-model.trim="create.phone">
            </div>

            <div style="grid-column:1 / -1; margin-top:6px;">
              <button type="submit" style="padding:8px 12px;border:none;border-radius:6px;background:#2b6cb0;color:#fff;cursor:pointer;">
                Add Visitor
              </button>
            </div>
          </form>
        </details>

        <!-- List + inline edit -->
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f8f8;border-bottom:1px solid #ddd;">
                <th style="text-align:left;padding:8px;">Name</th>
                <th style="text-align:left;padding:8px;">Username</th>
                <th style="text-align:left;padding:8px;">Email</th>
                <th style="text-align:left;padding:8px;">Phone</th>
                <th style="text-align:left;padding:8px;">Status</th>
                <th style="text-align:left;padding:8px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="v in visitors" :key="v.id" style="border-bottom:1px solid #eee;">
                <!-- view row -->
                <template v-if="editId !== v.id">
                  <td style="padding:8px;">{{ v.full_name }}</td>
                  <td style="padding:8px;">{{ v.username }}</td>
                  <td style="padding:8px;">{{ v.email || '' }}</td>
                  <td style="padding:8px;">{{ v.phone || '' }}</td>
                  <td style="padding:8px;">{{ v.is_active ? 'Active' : 'Inactive' }}</td>
                  <td style="padding:8px;">
                    <a :href="'relationships.php?view=visitor&id='+v.id">Relationships</a>
                    <span> | </span>
                    <button @click="beginEdit(v)" style="padding:6px 10px;border:none;border-radius:6px;background:#555;color:#fff;cursor:pointer;">Edit</button>
                    <button @click="doDelete(v)" style="padding:6px 10px;border:none;border-radius:6px;background:#dc3545;color:#fff;cursor:pointer;margin-left:6px;">Delete</button>
                  </td>
                </template>

                <!-- edit row -->
                <template v-else>
                  <td style="padding:8px;"><input v-model.trim="edit.full_name"></td>
                  <td style="padding:8px;"><input v-model.trim="edit.username"></td>
                  <td style="padding:8px;"><input v-model.trim="edit.email"></td>
                  <td style="padding:8px;"><input v-model.trim="edit.phone"></td>
                  <td style="padding:8px;">
                    <select v-model.number="edit.is_active">
                      <option :value="1">Active</option>
                      <option :value="0">Inactive</option>
                    </select>
                  </td>
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
  // Compute base paths (works if app is in / or /v2 etc.)
  var APP_BASE = "<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); ?>";
  var API_BASE = APP_BASE + "/api";

  const api = axios.create({ baseURL: API_BASE, withCredentials: true });

  function toInt(v){ return v === '' || v === null || typeof v === 'undefined' ? null : (v|0); }

  Vue.createApp({
    data(){
      return {
        loading: true,
        me: null,
        visitors: [],
        err: '',
        msg: '',
        editId: 0,
        edit: {
          id: 0, username:'', full_name:'', email:'', is_active:1, phone:'', reset_password:''
        },
        create: {
          username:'', full_name:'', email:'', password:'',
          is_active:1, phone:''
        }
      };
    },
    async mounted(){
      try {
        await this.ensureCsrf();
        await this.loadMe();
        if (!this.me || this.me.role !== 'ADMIN') { this.loading=false; return; }
        await this.loadVisitors();
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
        } catch (e) {
          this.me = null;
        }
      },
      async loadVisitors(){
        const r = await api.get('/index.php', { params:{ r:'/v1/visitors', __ts:Date.now() } });
        this.visitors = (r.data && r.data.data) ? r.data.data : [];
      },

      beginEdit(v){
        this.err=''; this.msg='';
        this.editId = v.id;
        this.edit = {
          id: v.id,
          username: v.username || '',
          full_name: v.full_name || '',
          email: v.email || '',
          is_active: (v.is_active ? 1 : 0),
          phone: v.phone || '',
          reset_password: ''
        };
      },
      cancelEdit(){
        this.editId = 0;
        this.edit = { id:0, username:'', full_name:'', email:'', is_active:1, phone:'', reset_password:'' };
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
            phone: this.edit.phone || null
          };
          if (this.edit.reset_password) {
            payload.password = this.edit.reset_password;
          }
          await api.put('/index.php', payload, { params:{ r:'/v1/visitors/' + id } });
          this.msg = 'Saved.';
          this.cancelEdit();
          await this.loadVisitors();
        } catch (e) {
          this.err = this.pickError(e);
        }
      },
      async createVisitor(){
        try {
          this.err=''; this.msg='';
          const payload = {
            username: this.create.username,
            full_name: this.create.full_name,
            email: this.create.email || null,
            password: this.create.password,     // temp password
            is_active: this.create.is_active|0,
            phone: this.create.phone || null
          };
          await api.post('/index.php', payload, { params:{ r:'/v1/visitors' } });
          this.msg = 'Visitor created.';
          this.create = { username:'', full_name:'', email:'', password:'', is_active:1, phone:'' };
          await this.loadVisitors();
        } catch (e) {
          this.err = this.pickError(e);
        }
      },
      async doDelete(v){
        if (!confirm('Delete visitor "' + (v.full_name||v.username) + '"?')) return;
        try {
          this.err=''; this.msg='';
          await api.delete('/index.php', { params:{ r:'/v1/visitors/' + v.id } });
          this.msg = 'Deleted.';
          await this.loadVisitors();
        } catch (e) {
          this.err = this.pickError(e);
        }
      }
    }
  }).mount('#app');
</script>
<?php renderFooter(); ?>
