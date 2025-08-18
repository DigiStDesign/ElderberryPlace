<?php
require_once __DIR__ . '/includes/layout.php';
start_secure_session();
$phpUser = current_user();
renderHeader('Relationships');
?>
<main style="max-width:1100px;margin:24px auto;padding:16px;">
  <!-- Prehydrate current user for quick gate -->
  <script>
    window.__ME = <?php
      echo json_encode($phpUser ? array(
        'id'=>(int)$phpUser['id'],
        'username'=>isset($phpUser['username'])?$phpUser['username']:null,
        'full_name'=>isset($phpUser['full_name'])?$phpUser['full_name']:null,
        'role'=>$phpUser['role']
      ) : null);
    ?>;
  </script>

  <div id="app">
    <!-- messages -->
    <div v-if="msg" style="background:#ecfdf3;border:1px solid #badbcc;padding:10px;border-radius:6px;margin-bottom:12px;">
      {{ msg }}
    </div>
    <div v-if="err" style="background:#fdecea;border:1px solid #f5c2c7;padding:10px;border-radius:6px;margin-bottom:12px;color:#b00020;">
      {{ err }}
    </div>

    <div v-if="loading">Loading…</div>

    <template v-else>
      <!-- Gate: only ADMIN/STAFF can manage -->
      <div v-if="!me || (me.role!=='ADMIN' && me.role!=='STAFF')">
        <p>Admins/Staff only. <a href="index.php">Back to home</a></p>
      </div>

      <div v-else>
        <h2 style="margin:0 0 12px;">Relationships</h2>

        <!-- ADMIN context chooser -->
        <div v-if="isAdmin" style="background:#f9fafb;border:1px solid #e5e7eb;padding:10px;border-radius:8px;margin-bottom:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
          <div>
            <label>View by<br>
              <select v-model="view" @change="onViewChanged">
                <option value="resident">Resident</option>
                <option value="visitor">Visitor</option>
              </select>
            </label>
          </div>
          <div>
            <label v-if="view==='resident'">Resident<br>
              <select v-model.number="ownerId">
                <option :value="0">-- Choose a resident --</option>
                <option v-for="r in allResidents" :key="'ar'+r.id" :value="r.id">{{ r.full_name }}</option>
              </select>
            </label>
            <label v-else>Visitor<br>
              <select v-model.number="ownerId">
                <option :value="0">-- Choose a visitor --</option>
                <option v-for="v in allVisitors" :key="'av'+v.id" :value="v.id">{{ v.full_name }}</option>
              </select>
            </label>
          </div>
          <div>
            <button @click="applyAdminContext" style="padding:8px 12px;border:none;border-radius:6px;background:#2b6cb0;color:#fff;cursor:pointer;">Load</button>
          </div>
          <div style="margin-left:auto;opacity:.7">Role: ADMIN</div>
        </div>

        <!-- Non-admin/staff breadcrumb (kept) -->
        <div v-else style="margin-bottom:10px;">
          <strong>Context:</strong>
          <span v-if="view==='resident'">Resident #{{ ownerId }}</span>
          <span v-else>Visitor #{{ ownerId }}</span>
          &nbsp;|&nbsp;
          <a :href="APP_BASE + '/residents.php'">Residents</a>
          &nbsp;|&nbsp;
          <a :href="APP_BASE + '/visitors.php'">Visitors</a>
        </div>

        <!-- Add relationship -->
        <details style="margin:12px 0;" :open="ownerId>0">
          <summary style="cursor:pointer;">Add Relationship</summary>

          <div v-if="ownerId===0" style="background:#fff3cd;border:1px solid #ffe69c;color:#664d03;padding:10px;border-radius:6px;margin-top:10px;">
            Choose a {{ view==='resident' ? 'resident' : 'visitor' }} first.
          </div>

          <form v-else @submit.prevent="addRel" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-top:10px;">
            <div v-if="view==='resident'">
              <label>Visitor<br>
                <select v-model.number="form.visitor_user_id" required>
                  <option value="">-- Visitor --</option>
                  <option v-for="v in visitors" :key="'v'+v.id" :value="v.id">{{ v.full_name }}</option>
                </select>
              </label>
            </div>
            <div v-else>
              <label>Resident<br>
                <select v-model.number="form.resident_user_id" required>
                  <option value="">-- Resident --</option>
                  <option v-for="r in residents" :key="'r'+r.id" :value="r.id">{{ r.full_name }}</option>
                </select>
              </label>
            </div>

            <div>
              <label>Type<br>
                <select v-model.number="form.relationship_type_id" required>
                  <option value="">-- Type --</option>
                  <option v-for="t in types" :key="'t'+t.id" :value="t.id">{{ t.name }}</option>
                </select>
              </label>
            </div>

            <div><label><input type="checkbox" v-model="form.is_primary_contact"> Primary?</label></div>
            <div><label>Start<br><input type="date" v-model="form.start_date"></label></div>
            <div><label>End<br><input type="date" v-model="form.end_date"></label></div>
            <div style="flex:1;min-width:260px;"><label>Notes<br><input v-model.trim="form.notes" style="width:100%"></label></div>

            <div><button type="submit" style="padding:8px 12px;border:none;border-radius:6px;background:#2b6cb0;color:#fff;cursor:pointer;">Add</button></div>
          </form>
        </details>

        <!-- Existing links -->
        <div v-if="ownerId===0" style="background:#eef2ff;border:1px solid #c7d2fe;padding:10px;border-radius:6px;">
          Pick a {{ view==='resident'?'resident':'visitor' }} to see relationships.
        </div>

        <div v-else-if="rows.length===0" style="background:#f8fafc;border:1px solid #e5e7eb;padding:10px;border-radius:6px;">
          No relationships set.
        </div>

        <div v-else style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f8f8;border-bottom:1px solid #ddd;">
                <th style="text-align:left;padding:8px;">Person</th>
                <th style="text-align:left;padding:8px;">Type</th>
                <th style="text-align:left;padding:8px;">Primary</th>
                <th style="text-align:left;padding:8px;">Start</th>
                <th style="text-align:left;padding:8px;">End</th>
                <th style="text-align:left;padding:8px;">Notes</th>
                <th style="text-align:left;padding:8px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in rows" :key="r.id" style="border-bottom:1px solid #eee;">
                <td style="padding:8px;">{{ r.person_name }}</td>
                <td style="padding:8px;">{{ r.relationship_name }}</td>
                <td style="padding:8px;">{{ r.is_primary_contact ? 'Yes' : 'No' }}</td>
                <td style="padding:8px;">{{ r.start_date || '' }}</td>
                <td style="padding:8px;">{{ r.end_date || '' }}</td>
                <td style="padding:8px;white-space:pre-line;">{{ r.notes || '' }}</td>
                <td style="padding:8px;">
                  <button @click="removeRel(r)" style="padding:6px 10px;border:none;border-radius:6px;background:#dc3545;color:#fff;cursor:pointer;">Remove</button>
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

  function pickError(e){
    return (e && e.response && e.response.data && e.response.data.error && e.response.data.error.message)
      ? e.response.data.error.message
      : (e && e.message ? e.message : 'Unexpected error');
  }

  async function apiGetCsrf(){
    const r = await api.get('/index.php', { params:{ r:'/v1/csrf', __ts:Date.now() } });
    const token = r.data && r.data.data ? r.data.data.csrf : '';
    if (token) api.defaults.headers.common['X-CSRF-Token'] = token;
    return token;
  }

  function unpack(r){
    if (!r || !r.data) return [];
    if (r.data.data && r.data.data.items) return r.data.data.items;
    if (r.data.data) return r.data.data;
    return r.data;
  }

  Vue.createApp({
    data(){
      var qs = new URLSearchParams(location.search);
      var initialView = (qs.get('view') || 'resident') === 'visitor' ? 'visitor' : 'resident';
      var initialOwner = parseInt(qs.get('id') || '0', 10);
      return {
        APP_BASE: APP_BASE,
        loading: true,
        me: window.__ME || null,

        // context
        view: initialView,
        ownerId: initialOwner,

        // admin lists
        allResidents: [],
        allVisitors: [],

        // data for the add form
        types: [],
        visitors: [],
        residents: [],
        rows: [],

        // form
        form: {
          visitor_user_id: '',
          resident_user_id: '',
          relationship_type_id: '',
          is_primary_contact: false,
          start_date: '',
          end_date: '',
          notes: ''
        },

        // messages
        err: '',
        msg: ''
      };
    },

    computed:{
      isAdmin(){ return this.me && this.me.role === 'ADMIN'; }
    },

    async mounted(){
      try{
        await apiGetCsrf();
        if (!this.me) await this.loadMe();
        if (!this.me || (this.me.role!=='ADMIN' && this.me.role!=='STAFF')) { this.loading = false; return; }

        // Always load types
        await this.loadTypes();

        // If admin, load the chooser lists up-front
        if (this.isAdmin) {
          await Promise.all([this.loadAllResidents(), this.loadAllVisitors()]);
          // Don’t auto-pick; let admin choose. If URL had an id, honor it.
        }

        // For initial view, load the “other side” people for the add form
        await this.loadPeople();

        // If we already have an ownerId (from URL or admin selection), load links
        if (this.ownerId) await this.loadLinks();
      }catch(e){
        this.err = pickError(e);
      }finally{
        this.loading = false;
      }
    },

    methods:{
      async loadMe(){
        const r = await api.get('/index.php', { params:{ r:'/v1/me', __ts:Date.now() } });
        this.me = (r.data && r.data.data) ? r.data.data.user : null;
      },
      async loadTypes(){
        const r = await api.get('/index.php', { params:{ r:'/v1/relationships/types', __ts:Date.now() } });
        // controller returns {items:[...]} or [...]
        var rows = unpack(r);
        // normalize to array of {id,name}
        this.types = (rows.items ? rows.items : rows);
      },
      async loadAllResidents(){
        const r = await api.get('/index.php', { params:{ r:'/v1/residents', __ts:Date.now() } });
        this.allResidents = unpack(r) || [];
      },
      async loadAllVisitors(){
        const r = await api.get('/index.php', { params:{ r:'/v1/visitors', __ts:Date.now() } });
        this.allVisitors = unpack(r) || [];
      },
      async loadPeople(){
        if (this.view === 'resident') {
          const v = await api.get('/index.php', { params:{ r:'/v1/visitors', __ts:Date.now() } });
          this.visitors = unpack(v) || [];
        } else {
          const res = await api.get('/index.php', { params:{ r:'/v1/residents', __ts:Date.now() } });
          this.residents = unpack(res) || [];
        }
      },
      async loadLinks(){
        if (!this.ownerId) { this.rows = []; return; }
        const path = this.view === 'resident'
          ? '/v1/relationships/by-resident/' + this.ownerId
          : '/v1/relationships/by-visitor/' + this.ownerId;
        const r = await api.get('/index.php', { params:{ r:path, __ts:Date.now() } });
        this.rows = unpack(r) || [];
      },

      /* admin toolbar handlers */
      onViewChanged(){
        // reset owner when switching list type
        this.ownerId = 0;
        // refresh the “other side” list for the add form
        this.loadPeople().catch(()=>{});
      },
      async applyAdminContext(){
        try{
          this.err=''; this.msg='';
          if (!this.ownerId) { this.rows = []; return; }
          // update URL (nice UX)
          var sp = new URLSearchParams(location.search);
          sp.set('view', this.view);
          sp.set('id', String(this.ownerId));
          history.replaceState(null, '', location.pathname + '?' + sp.toString());
          await this.loadLinks();
        }catch(e){ this.err = pickError(e); }
      },

      /* add & remove */
      async addRel(){
        try{
          this.err=''; this.msg='';
          if (!this.ownerId) { this.err='Pick a context person first.'; return; }

          var payload = {
            relationship_type_id: this.form.relationship_type_id|0,
            is_primary_contact: this.form.is_primary_contact ? 1 : 0,
            start_date: this.form.start_date || null,
            end_date: this.form.end_date || null,
            notes: this.form.notes || null
          };
          if (this.view === 'resident') {
            payload.resident_user_id = this.ownerId;
            payload.visitor_user_id  = this.form.visitor_user_id|0;
          } else {
            payload.visitor_user_id  = this.ownerId;
            payload.resident_user_id = this.form.resident_user_id|0;
          }
          await api.post('/index.php', payload, { params:{ r:'/v1/relationships' } });
          this.msg = 'Relationship added.';
          this.form = { visitor_user_id:'', resident_user_id:'', relationship_type_id:'', is_primary_contact:false, start_date:'', end_date:'', notes:'' };
          await this.loadLinks();
        }catch(e){
          this.err = pickError(e);
        }
      },

      async removeRel(row){
        if (!confirm('Remove this relationship?')) return;
        try{
          this.err=''; this.msg='';
          await api.delete('/index.php', { params:{ r:'/v1/relationships/' + row.id } });
          this.msg = 'Removed.';
          await this.loadLinks();
        }catch(e){
          this.err = pickError(e);
        }
      }
    }
  }).mount('#app');
</script>
<?php renderFooter(); ?>
