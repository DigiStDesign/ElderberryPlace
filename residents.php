<?php
require_once __DIR__ . '/includes/layout.php';
renderHeader('Residents');
?>
<main style="max-width:1000px;margin:20px auto;">
  <div id="app">
    <h2>Residents</h2>

    <div v-if="loading">Loading…</div>

    <template v-else>
      <div v-if="err" style="background:#fdecea;border:1px solid #f5c2c7;color:#b00020;padding:10px;border-radius:6px;margin:12px 0;">
        {{ err }}
      </div>

      <div style="display:flex;gap:20px;align-items:flex-start;">
        <!-- List -->
        <div style="flex:1;">
          <table border="1" cellpadding="6" cellspacing="0" style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f8f8;">
                <th>Full name</th><th>Username</th><th>Room</th><th>DOB</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in rows" :key="r.id">
                <td>{{ r.full_name }}</td>
                <td>{{ r.username }}</td>
                <td>{{ r.room_number || '' }}</td>
                <td>{{ r.dob || '' }}</td>
                <td>
                  <a href="#" @click.prevent="edit(r)">Edit</a> |
                  <a :href="APP_BASE + '/relationships.php?view=resident&id=' + r.id">Relationships</a> |
                  <a href="#" @click.prevent="del(r)">Delete</a>
                </td>
              </tr>
              <tr v-if="rows.length===0">
                <td colspan="5" style="opacity:.7;">No residents yet.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Form -->
        <div style="flex:0 0 360px;">
          <h3 style="margin:0 0 10px;">{{ form.id ? 'Edit Resident' : 'Add Resident' }}</h3>
          <form @submit.prevent="save" style="display:grid;gap:8px;">
            <input v-model.trim="form.username" placeholder="Username" required />
            <input v-model.trim="form.full_name" placeholder="Full name" required />
            <input v-model.trim="form.email" placeholder="Email (optional)" />
            <input v-model.trim="form.room_number" placeholder="Room number" />
            <input v-model.trim="form.dob" placeholder="DOB YYYY-MM-DD" />
            <input v-model.trim="form.password"
                   :placeholder="form.id ? 'Reset password (optional)' : 'Temp password'"
                   :required="!form.id" />
            <div style="display:flex;gap:8px;">
              <button type="submit" style="padding:8px 12px;border:none;border-radius:6px;background:#2b6cb0;color:#fff;cursor:pointer;">
                {{ form.id ? 'Update' : 'Add' }}
              </button>
              <button @click.prevent="clear" type="button" style="padding:8px 12px;border:none;border-radius:6px;background:#6c757d;color:#fff;cursor:pointer;">
                Clear
              </button>
            </div>
            <p v-if="msg" style="color:#0a7;margin:4px 0 0;">{{ msg }}</p>
          </form>
        </div>
      </div>
    </template>
  </div>
</main>

<script src="https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.7/dist/axios.min.js"></script>
<script src="assets/app.js"></script>
<script>
Vue.createApp({
  data(){
    return {
      APP_BASE: APP_BASE,
      loading: true,
      rows: [],
      form: { id:null, username:'', full_name:'', email:'', room_number:'', dob:'', password:'' },
      msg: '',
      err: ''
    };
  },
  methods:{
    async init(){
      try {
        await window.apiGetCsrf();      // set CSRF header for this tab
        await this.load();              // load initial list
      } catch(e) { this.err = this.msgFrom(e); }
      finally { this.loading = false; }
    },
    async load(){
      const r = await apiGet('/residents');
      const data = r.data && r.data.data ? r.data.data : [];
      this.rows = data.items ? data.items : data; // accept either shape
    },
    edit(row){
      this.msg=''; this.err='';
      this.form = {
        id: row.id,
        username: row.username || '',
        full_name: row.full_name || '',
        email: row.email || '',
        room_number: row.room_number || '',
        dob: row.dob || '',
        password: ''
      };
    },
    clear(){
      this.msg=''; this.err='';
      this.form = { id:null, username:'', full_name:'', email:'', room_number:'', dob:'', password:'' };
    },
    async save(){
      try{
        this.msg=''; this.err='';
        if (this.form.id){
          // UPDATE via PUT
          var payload = {
            username: this.form.username,
            full_name: this.form.full_name,
            email: this.form.email || null,
            room_number: this.form.room_number || null,
            dob: this.form.dob || null
          };
          if (this.form.password) payload.password = this.form.password;
          await apiPut('/residents/' + this.form.id, payload);
          this.msg = 'Updated.';
        } else {
          // CREATE via POST
          var body = {
            username: this.form.username,
            full_name: this.form.full_name,
            email: this.form.email || null,
            room_number: this.form.room_number || null,
            dob: this.form.dob || null,
            password: this.form.password
          };
          await apiPost('/residents', body);
          this.msg = 'Created.';
        }
        this.clear();
        await this.load();
      } catch(e){ this.err = this.msgFrom(e); }
    },
    async del(row){
      if (!confirm('Delete this resident?')) return;
      try {
        this.msg=''; this.err='';
        await apiDelete('/residents/' + row.id);
        this.msg = 'Deleted.';
        await this.load();
      } catch(e){ this.err = this.msgFrom(e); }
    },
    msgFrom(e){
      return (e && e.response && e.response.data && e.response.data.error && e.response.data.error.message)
        ? e.response.data.error.message
        : (e && e.message ? e.message : 'Unexpected error');
    }
  },
  mounted(){ this.init(); }
}).mount('#app');
</script>
<?php renderFooter(); ?>
