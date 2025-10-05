<?php
require_once __DIR__ . '/includes/layout.php';
renderHeader('Incidents');
?>
<main class="container" style="max-width:1100px;margin:20px auto;">
  <div id="app-inc">
    <h2>Incidents</h2>

    <!-- Filters -->
    <form @submit.prevent="load" class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
      <input v-model.number="resident_id" type="number" min="1" placeholder="Resident ID">
      <input v-model.trim="from" placeholder="From YYYY-MM-DD">
      <input v-model.trim="to" placeholder="To YYYY-MM-DD">
      <select v-model="type">
        <option value="">Type (any)</option>
        <option>medication</option><option>fall</option><option>behaviour</option><option>infection</option><option>other</option>
      </select>
      <select v-model="severity">
        <option value="">Severity (any)</option>
        <option>low</option><option>moderate</option><option>high</option><option>critical</option>
      </select>
      <button class="btn btn--primary">Filter</button>
    </form>

    <div v-if="err" class="card" style="border-color:#f5c2c7;color:#b00020;">{{ err }}</div>

    <!-- List -->
    <div class="card" style="overflow:auto;">
      <table class="table">
        <thead>
          <tr>
            <th>ID</th><th>Resident</th><th>Type</th><th>Severity</th><th>Occurred</th><th>Description</th><th>Action taken</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="i in rows" :key="i.id">
            <td class="num">{{ i.id }}</td>
            <td>{{ i.resident_name || ('#'+(i.resident_user_id||'')) }}</td>
            <td>{{ i.type }}</td>
            <td>{{ i.severity }}</td>
            <td>{{ i.occurred_at }}</td>
            <td>{{ i.description || '' }}</td>
            <td>{{ i.action_taken || '' }}</td>
          </tr>
          <tr v-if="rows.length===0"><td colspan="7" class="muted">No incidents.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Create -->
    <h3 style="margin-top:18px;">Report new incident</h3>
    <div class="card">
      <form @submit.prevent="create" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <input v-model.number="newItem.resident_user_id" type="number" min="1" placeholder="Resident ID (optional)">
        <select v-model="newItem.type" required>
          <option disabled value="">Type…</option>
          <option>medication</option><option>fall</option><option>behaviour</option><option>infection</option><option>other</option>
        </select>
        <select v-model="newItem.severity" required>
          <option disabled value="">Severity…</option>
          <option>low</option><option>moderate</option><option>high</option><option>critical</option>
        </select>
        <input v-model.trim="newItem.occurred_at" placeholder="Occurred at (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)" required style="grid-column:1 / -1">
        <input v-model.trim="newItem.description" placeholder="Description (optional)" style="grid-column:1 / -1">
        <input v-model.trim="newItem.action_taken" placeholder="Action taken (optional)" style="grid-column:1 / -1">

        <div style="grid-column:1 / -1;display:flex;gap:8px;">
          <button class="btn btn--primary">Create</button>
          <span class="muted" v-if="msg">{{ msg }}</span>
        </div>
      </form>
    </div>
  </div>
</main>

<script src="https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.7/dist/axios.min.js"></script>
<script src="assets/app.js"></script>
<script>
Vue.createApp({
  data(){ 
    return {
      rows:[], err:'', msg:'',
      resident_id:null, from:'', to:'', type:'', severity:'',
      newItem:{ resident_user_id:null, type:'', severity:'', occurred_at:'', reported_by:0, description:'', action_taken:'' }
    };
  },
  methods:{
    async load(){
      try{
        this.err='';
        var p={};
        if(this.resident_id) p.resident_id=this.resident_id;
        if(this.from) p.from=this.from; if(this.to) p.to=this.to;
        if(this.type) p.type=this.type; if(this.severity) p.severity=this.severity;
        var r=await apiGet('/incidents', p);
        var d=r.data&&r.data.data?r.data.data:r.data;
        this.rows=(d&&d.items)?d.items:[];
      }catch(e){ this.err=this.msgFrom(e); }
    },
    async create(){
      try{
        this.msg=''; this.err='';
        // reported_by: whoever is logged in
        var me=null; try{ var r=await apiGet('/me'); var dd=r.data&&r.data.data?r.data.data:r.data; me=dd&&dd.user?dd.user:null; }catch(e){}
        this.newItem.reported_by = me ? me.id : 0;
        await apiPost('/incidents', this.newItem);
        this.msg='Created.';
        this.newItem={ resident_user_id:null, type:'', severity:'', occurred_at:'', reported_by:0, description:'', action_taken:'' };
        await this.load();
      }catch(e){ this.err=this.msgFrom(e); }
    },
    msgFrom(e){ return (e&&e.response&&e.response.data&&e.response.data.error&&e.response.data.error.message)?e.response.data.error.message:(e&&e.message?e.message:'Error'); }
  },
  mounted(){ this.load(); }
}).mount('#app-inc');
</script>
<?php renderFooter(); ?>
