<?php
require_once __DIR__ . '/includes/layout.php';
renderHeader('Medication Alerts');
?>
<main class="container" style="max-width:1100px;margin:20px auto;">
  <div id="app-alerts">
    <h2>Medication Alerts</h2>

    <form @submit.prevent="load" class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
      <label>State
        <select v-model="state">
          <option value="">(any)</option>
          <option>open</option>
          <option>resolved</option>
        </select>
      </label>
      <label>Type
        <select v-model="type">
          <option value="">(any)</option>
          <option value="overdue">overdue</option>
          <option value="allergy">allergy</option>
          <option value="prn_limit">prn_limit</option>
        </select>
      </label>
      <label>Resident ID
        <input v-model.number="resident_id" type="number" min="1" placeholder="optional">
      </label>
      <button class="btn btn--primary">Filter</button>
    </form>

    <div v-if="err" class="card" style="border-color:#f5c2c7;color:#b00020;">{{ err }}</div>

    <div class="card" style="overflow:auto;">
      <table class="table">
        <thead><tr>
          <th>ID</th><th>Type</th><th>Message</th><th>Resident</th><th>Due at</th><th>State</th><th>Action</th>
        </tr></thead>
        <tbody>
          <tr v-for="a in rows" :key="a.id">
            <td class="num">{{ a.id }}</td>
            <td>{{ a.type }}</td>
            <td>{{ a.message }}</td>
            <td>{{ a.resident_name || ('#'+a.resident_user_id) }}</td>
            <td>{{ a.due_at || '' }}</td>
            <td>{{ a.state }}</td>
            <td>
              <button class="btn btn--secondary" @click="toggle(a)">{{ a.state==='open' ? 'Resolve' : 'Reopen' }}</button>
            </td>
          </tr>
          <tr v-if="rows.length===0"><td colspan="7" class="muted">No alerts.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</main>

<script src="https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.7/dist/axios.min.js"></script>
<script src="assets/app.js"></script>
<script>
Vue.createApp({
  data(){ return { state:'open', type:'', resident_id:null, rows:[], err:'' }; },
  methods:{
    async load(){
      try{
        this.err='';
        var p={}; if(this.state) p.state=this.state; if(this.type) p.type=this.type; if(this.resident_id) p.resident_id=this.resident_id;
        var r=await apiGet('/med-alerts', p);
        var d=r.data&&r.data.data?r.data.data:r.data;
        this.rows=(d&&d.items)?d.items:[];
      }catch(e){ this.err=this.msgFrom(e); }
    },
    async toggle(a){
      try{
        var newState = a.state==='open' ? 'resolved' : 'open';
        await apiPatch('/med-alerts/'+a.id, { state:newState, resolved_by:null });
        a.state = newState;
      }catch(e){ alert(this.msgFrom(e)); }
    },
    msgFrom(e){ return (e&&e.response&&e.response.data&&e.response.data.error&&e.response.data.error.message)?e.response.data.error.message:(e&&e.message?e.message:'Error'); }
  },
  mounted(){ this.load(); }
}).mount('#app-alerts');
</script>
<?php renderFooter(); ?>
