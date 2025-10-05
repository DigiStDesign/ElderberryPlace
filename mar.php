<?php
require_once __DIR__ . '/includes/layout.php';
renderHeader('Medication Rounds');
?>
<main class="container" style="max-width:1100px;margin:20px auto;">
  <div id="app-mar">
    <h2>Medication Rounds</h2>

    <div class="card" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
      <label>Resident
        <select v-model.number="residentId" @change="load" style="min-width:240px;">
          <option v-for="r in residents" :value="r.id">{{ r.full_name }} ({{ r.username }})</option>
        </select>
      </label>
      <label>From
        <input v-model="from" placeholder="YYYY-MM-DD 00:00:00">
      </label>
      <label>To
        <input v-model="to" placeholder="YYYY-MM-DD 23:59:59">
      </label>
      <button class="btn btn--primary" @click="load">Refresh</button>
    </div>

    <div v-if="err" class="card" style="border-color:#f5c2c7;color:#b00020;">{{ err }}</div>

    <div class="card" style="overflow:auto;">
      <table class="table">
        <thead>
          <tr>
            <th>Due</th><th>Medication</th><th>Dose/Route</th><th>Window</th><th>Outcome</th><th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="it in rows" :key="it.schedule_id">
            <td>{{ it.due_at }}</td>
            <td>
              <strong>{{ it.generic_name }}</strong>
              <div class="muted">{{ it.brand_name }} • {{ it.form }} {{ it.strength }}</div>
            </td>
            <td>{{ it.dose }} <span class="muted">/ {{ it.route || '-' }}</span></td>
            <td class="num">{{ it.window_minutes }} min</td>
            <td>{{ it.outcome || '' }}</td>
            <td>
              <button class="btn btn--secondary" @click="openAdmin(it)" :disabled="it.outcome">Administer</button>
            </td>
          </tr>
          <tr v-if="rows.length===0"><td colspan="6" class="muted">No items due.</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Administer panel -->
    <div v-if="panel" class="card" style="margin-top:12px;">
      <h3 style="margin-top:0;">Record administration</h3>
      <p><strong>{{ panel.generic_name }}</strong> — {{ panel.dose }} ({{ panel.route||'-' }}) • due {{ panel.due_at }}</p>
      <form @submit.prevent="submitAdmin" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <label>Outcome
          <select v-model="adm.outcome" required>
            <option value="given">given</option>
            <option value="refused">refused</option>
            <option value="withheld">withheld</option>
            <option value="missed">missed</option>
          </select>
        </label>
        <label>Dose given (optional)
          <input v-model.trim="adm.dose_given" placeholder="e.g., 1 tab">
        </label>
        <label style="grid-column:1 / -1;">Notes
          <input v-model.trim="adm.notes" placeholder="Notes (optional)">
        </label>
        <label>Witness user id (optional)
          <input v-model.number="adm.witness_user_id" type="number" min="1">
        </label>
        <div style="grid-column:1 / -1;display:flex;gap:8px;">
          <button class="btn btn--primary">Save</button>
          <button class="btn btn--muted" @click.prevent="panel=null">Cancel</button>
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
function todayRange(){
  var d=new Date(), y=d.getFullYear(), m=('0'+(d.getMonth()+1)).slice(-2), day=('0'+d.getDate()).slice(-2);
  return { from:y+'-'+m+'-'+day+' 00:00:00', to:y+'-'+m+'-'+day+' 23:59:59' };
}
Vue.createApp({
  data(){ 
    var r=todayRange();
    return {
      residents:[], residentId:null, rows:[], err:'', msg:'',
      from:r.from, to:r.to,
      meId:null,
      panel:null,
      adm:{ outcome:'given', dose_given:'', notes:'', witness_user_id:null }
    }; 
  },
  methods:{
    async boot(){
      await window.apiGetCsrf();
      await this.loadResidents();
      await this.getMe();
      if (this.residents.length){ this.residentId=this.residents[0].id; this.load(); }
    },
    async getMe(){
      try{
        var r = await apiGet('/me'); // your API should expose /v1/me via helper
        var d = r.data && r.data.data ? r.data.data : r.data;
        this.meId = d && d.user ? d.user.id : null;
      }catch(e){}
    },
    async loadResidents(){
      var r=await apiGet('/residents');
      var d=r.data&&r.data.data?r.data.data:r.data;
      this.residents=d.items?d.items:d;
    },
    async load(){
      if(!this.residentId) return;
      try{
        this.err='';
        var r=await apiGet('/residents/'+this.residentId+'/med-due',{from:this.from,to:this.to});
        var d=r.data&&r.data.data?r.data.data:r.data;
        this.rows=(d&&d.items)?d.items:[];
      }catch(e){ this.err=this.msgFrom(e); }
    },
    openAdmin(item){
      this.msg=''; this.adm={ outcome:'given', dose_given:'', notes:'', witness_user_id:null };
      this.panel = item;
    },
    async submitAdmin(){
      try{
        var body={
          outcome:this.adm.outcome,
          dose_given:this.adm.dose_given||null,
          notes:this.adm.notes||null,
          staff_user_id:this.meId || 0,
          witness_user_id:this.adm.witness_user_id||null
        };
        await apiPost('/med-schedule/'+this.panel.schedule_id+'/administer', body);
        this.msg='Saved.';
        this.panel=null; await this.load();
      }catch(e){ this.msg=this.msgFrom(e); }
    },
    msgFrom(e){
      return (e&&e.response&&e.response.data&&e.response.data.error&&e.response.data.error.message)?e.response.data.error.message:(e&&e.message?e.message:'Error');
    }
  },
  mounted(){ this.boot(); }
}).mount('#app-mar');
</script>
<?php renderFooter(); ?>
