<?php
require_once __DIR__ . '/includes/layout.php';
renderHeader('Manage Service Categories');
?>
<main style="max-width:820px;margin:24px auto;padding:16px;">
  <div id="app">
    <!-- flash + errors -->
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
        <h2 style="margin:0 0 12px;">Service Categories</h2>

        <!-- add -->
        <details style="margin: 12px 0;">
          <summary style="cursor:pointer;">Add Category</summary>
          <form @submit.prevent="createCat" style="display:flex;gap:10px;margin-top:10px;align-items:center;">
            <input v-model.trim="create.name" placeholder="Category name" required />
            <button type="submit" style="padding:8px 12px;border:none;border-radius:6px;background:#2b6cb0;color:#fff;cursor:pointer;">Add</button>
          </form>
        </details>

        <!-- list -->
        <div style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f8f8;border-bottom:1px solid #ddd;">
                <th style="text-align:left;padding:8px;">Name</th>
                <th style="text-align:left;padding:8px;width:140px;">Services</th>
                <th style="text-align:left;padding:8px;width:220px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in rows" :key="c.id" style="border-bottom:1px solid #eee;">
                <template v-if="editId !== c.id">
                  <td style="padding:8px;">{{ c.name }}</td>
                  <td style="padding:8px;">{{ c.service_count || 0 }}</td>
                  <td style="padding:8px;">
                    <button @click="beginEdit(c)" style="padding:6px 10px;border:none;border-radius:6px;background:#555;color:#fff;cursor:pointer;">Edit</button>
                    <button @click="doDelete(c)" :disabled="(c.service_count||0) > 0"
                            title="Cannot delete a category that has services"
                            style="padding:6px 10px;border:none;border-radius:6px;background:#dc3545;color:#fff;cursor:pointer;margin-left:6px;">
                      Delete
                    </button>
                  </td>
                </template>

                <template v-else>
                  <td style="padding:8px;"><input v-model.trim="edit.name" /></td>
                  <td style="padding:8px;">{{ c.service_count || 0 }}</td>
                  <td style="padding:8px;">
                    <button @click="saveEdit" style="padding:6px 10px;border:none;border-radius:6px;background:#2b6cb0;color:#fff;cursor:pointer;">Save</button>
                    <button @click="cancelEdit" style="padding:6px 10px;border:none;border-radius:6px;background:#6c757d;color:#fff;cursor:pointer;margin-left:6px;">Cancel</button>
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

  Vue.createApp({
    data(){
      return {
        loading: true,
        me: null,
        rows: [],
        err: '',
        msg: '',
        editId: 0,
        edit: { id:0, name:'' },
        create: { name:'' }
      };
    },
    async mounted(){
      try {
        await this.ensureCsrf();
        await this.loadMe();
        if (!this.me || this.me.role !== 'ADMIN') { this.loading=false; return; }
        await this.loadCats();
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
      async loadCats(){
        const r = await api.get('/index.php', { params:{ r:'/v1/categories', __ts:Date.now() } });
        this.rows = (r.data && r.data.data) ? r.data.data : [];
      },
      beginEdit(c){
        this.err=''; this.msg=''; this.editId = c.id; this.edit = { id:c.id, name:c.name || '' };
      },
      cancelEdit(){
        this.editId = 0; this.edit = { id:0, name:'' };
      },
      async saveEdit(){
        try {
          if (!this.edit.id || !this.edit.name) { this.err='Name is required.'; return; }
          await api.put('/index.php', { name:this.edit.name }, { params:{ r:'/v1/categories/'+this.edit.id } });
          this.msg='Saved.'; this.cancelEdit(); await this.loadCats();
        } catch(e){ this.err=this.pickError(e); }
      },
      async createCat(){
        try {
          if (!this.create.name) { this.err='Name is required.'; return; }
          await api.post('/index.php', { name:this.create.name }, { params:{ r:'/v1/categories' } });
          this.msg='Category created.'; this.create={ name:'' }; await this.loadCats();
        } catch(e){ this.err=this.pickError(e); }
      },
      async doDelete(c){
        if ((c.service_count||0) > 0) { this.err='Category is in use by services.'; return; }
        if (!confirm('Delete category "'+c.name+'"?')) return;
        try {
          await api.delete('/index.php', { params:{ r:'/v1/categories/'+c.id } });
          this.msg='Deleted.'; await this.loadCats();
        } catch(e){ this.err=this.pickError(e); }
      }
    }
  }).mount('#app');
</script>
<?php renderFooter(); ?>
