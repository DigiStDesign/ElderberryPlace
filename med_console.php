<?php
require_once __DIR__ . '/includes/layout.php';
renderHeader('Medication Console');
?>
<main class="container" style="max-width:1150px;margin:20px auto;">
  <div id="med-console">
    <h2 style="margin-bottom:12px;">Medication Console</h2>

    <!-- Tabs -->
    <nav class="card" style="display:flex;gap:8px;flex-wrap:wrap;padding:10px;">
      <button class="btn" :class="tab==='meds' ? 'btn--primary':'btn--secondary'" @click="tab='meds'">Medications</button>
      <button class="btn" :class="tab==='rx'   ? 'btn--primary':'btn--secondary'" @click="tab='rx'">Prescriptions</button>
      <button class="btn" :class="tab==='mar'  ? 'btn--primary':'btn--secondary'" @click="tab='mar'">Due</button>
      <button class="btn" :class="tab==='alerts' ? 'btn--primary':'btn--secondary'" @click="tab='alerts'">Alerts</button>

      <!-- shared resident picker for Rx & MAR (and optional for alerts) -->
      <div style="margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <label style="display:flex;gap:6px;align-items:center;">
          <span class="muted">Resident</span>
          <select v-model.number="residentId" style="min-width:240px;">
            <option v-for="r in residents" :key="r.id" :value="r.id">{{ r.full_name }} ({{ r.username }})</option>
          </select>
        </label>
        <button class="btn btn--secondary" @click="reloadCurrent">Reload</button>
      </div>
    </nav>

    <!-- ============ Medications (search) ============ -->
    <section v-show="tab==='meds'" style="margin-top:14px;">
      <form @submit.prevent="meds_load" class="card" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
        <input v-model.trim="meds.q" placeholder="Search name…" style="flex:1;min-width:220px;">
        <input v-model.trim="meds.form" placeholder="Form (e.g., tablet)">
        <input v-model.trim="meds.strength" placeholder="Strength (e.g., 500 mg)">
        <input v-model.number="meds.limit" type="number" min="1" max="100" title="limit" style="width:92px">
        <button class="btn btn--primary">Search</button>
      </form>

      <div v-if="meds.err" class="card" style="border-color:#f5c2c7;color:#b00020;">{{ meds.err }}</div>

      <div class="card" style="overflow:auto;">
        <table class="table">
          <thead><tr><th>ID</th><th>Generic</th><th>Brand</th><th>Form</th><th>Strength</th></tr></thead>
          <tbody>
            <tr v-for="m in meds.rows" :key="m.id">
              <td class="num">{{ m.id }}</td>
              <td>{{ m.generic_name }}</td>
              <td>{{ m.brand_name }}</td>
              <td>{{ m.form }}</td>
              <td>{{ m.strength }}</td>
            </tr>
            <tr v-if="meds.rows.length===0"><td colspan="5" class="muted">No results.</td></tr>
          </tbody>
        </table>
      </div>

      <div style="display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:8px;">
        <button class="btn btn--secondary" :disabled="meds.page<=1" @click="meds.page--; meds_load()">Prev</button>
        <span>Page {{ meds.page }}</span>
        <button class="btn btn--secondary" :disabled="meds.rows.length<meds.limit" @click="meds.page++; meds_load()">Next</button>
      </div>
    </section>

    <!-- ============ Prescriptions (list + create) ============ -->
    <section v-show="tab==='rx'" style="margin-top:14px;">
      <div v-if="rx.err" class="card" style="border-color:#f5c2c7;color:#b00020;">{{ rx.err }}</div>

      <h3 style="margin:10px 0;">Existing prescriptions</h3>
      <div class="card" style="overflow:auto;">
        <table class="table">
          <thead>
            <tr>
              <th>Medication</th><th>Dose</th><th>Route</th><th>PRN</th><th>Freq</th><th>Start</th><th>End</th><th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rx.rows" :key="r.id">
              <td><strong>{{ r.generic_name }}</strong><div class="muted">{{ r.brand_name }} • {{ r.form }} {{ r.strength }}</div></td>
              <td>{{ r.dose }}</td><td>{{ r.route || '-' }}</td><td>{{ r.prn ? 'Yes':'No' }}</td>
              <td>{{ r.frequency }}</td><td>{{ r.start_date }}</td><td>{{ r.end_date || '' }}</td><td>{{ r.status }}</td>
            </tr>
            <tr v-if="rx.rows.length===0"><td colspan="8" class="muted">No prescriptions.</td></tr>
          </tbody>
        </table>
      </div>

      <h3 style="margin:18px 0 10px;">New prescription</h3>
      <div class="card">
        <form @submit.prevent="rx_create" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <!-- Med search -->
          <div style="grid-column:1 / -1;">
            <label style="display:flex;gap:8px;align-items:center;">
              <input v-model.trim="rx.medSearch" placeholder="Search medication… (type then pick)" style="flex:1">
              <span v-if="rx.selectedMed" class="muted">Selected: {{ rxPickedMedLabel }}</span>
            </label>
            <div v-if="rx.medResults.length" class="card" style="margin-top:8px;max-height:210px;overflow:auto;">
              <table class="table">
                <thead><tr><th></th><th>Generic</th><th>Brand</th><th>Form</th><th>Strength</th></tr></thead>
                <tbody>
                  <tr v-for="m in rx.medResults" :key="m.id">
                    <td><button class="btn btn--secondary" @click.prevent="rx_pickMed(m)">Pick</button></td>
                    <td>{{ m.generic_name }}</td><td>{{ m.brand_name }}</td><td>{{ m.form }}</td><td>{{ m.strength }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <input v-model.trim="rx.form.dose" placeholder="Dose (e.g., 1 tab)" required>
          <input v-model.trim="rx.form.route" placeholder="Route (e.g., oral)">
          <input v-model.trim="rx.form.frequency" placeholder="Frequency (e.g., daily)" required>
          <label><input type="checkbox" v-model="rx.form.prn"> PRN</label>

          <input v-model.trim="rx.form.start_date" placeholder="Start date YYYY-MM-DD" required>
          <input v-model.trim="rx.form.end_date" placeholder="End date (optional) YYYY-MM-DD">

          <input v-model.trim="rx.timesText" placeholder="Times (comma HH:mm, e.g., 08:00,20:00)" style="grid-column:1 / -1">
          <input v-model.trim="rx.form.max_daily_dose" placeholder="Max daily dose (optional)">
          <input v-model.trim="rx.form.prescriber" placeholder="Prescriber (optional)">
          <input v-model.trim="rx.form.instructions" placeholder="Instructions (optional)" style="grid-column:1 / -1">

          <div style="grid-column:1 / -1;display:flex;gap:8px;">
            <button class="btn btn--primary" :disabled="!rx.form.medication_id || !residentId">Create</button>
            <span class="muted" v-if="rx.msg">{{ rx.msg }}</span>
          </div>
        </form>
      </div>
    </section>

    <!-- ============ MAR / Due ============ -->
    <section v-show="tab==='mar'" style="margin-top:14px;">
      <div class="card" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <label>From <input v-model="mar.from" placeholder="YYYY-MM-DD 00:00:00"></label>
        <label>To <input v-model="mar.to" placeholder="YYYY-MM-DD 23:59:59"></label>
        <button class="btn btn--primary" @click="mar_load">Refresh</button>
      </div>

      <div v-if="mar.err" class="card" style="border-color:#f5c2c7;color:#b00020;">{{ mar.err }}</div>

      <div class="card" style="overflow:auto;">
        <table class="table">
          <thead><tr><th>Due</th><th>Medication</th><th>Dose/Route</th><th>Window</th><th>Outcome</th><th>Action</th></tr></thead>
          <tbody>
            <tr v-for="it in mar.rows" :key="it.schedule_id">
              <td>{{ it.due_at }}</td>
              <td><strong>{{ it.generic_name }}</strong><div class="muted">{{ it.brand_name }} • {{ it.form }} {{ it.strength }}</div></td>
              <td>{{ it.dose }} <span class="muted">/ {{ it.route || '-' }}</span></td>
              <td class="num">{{ it.window_minutes }} min</td>
              <td>{{ it.outcome || '' }}</td>
              <td><button class="btn btn--secondary" @click="mar_open(it)" :disabled="it.outcome">Administer</button></td>
            </tr>
            <tr v-if="mar.rows.length===0"><td colspan="6" class="muted">No items due.</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Admin panel -->
      <div v-if="mar.panel" class="card" style="margin-top:12px;">
        <h3 style="margin-top:0;">Record administration</h3>
        <p><strong>{{ mar.panel.generic_name }}</strong> — {{ mar.panel.dose }} ({{ mar.panel.route||'-' }}) • due {{ mar.panel.due_at }}</p>
        <form @submit.prevent="mar_submit" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <label>Outcome
            <select v-model="mar.adm.outcome" required>
              <option value="given">given</option><option value="refused">refused</option>
              <option value="withheld">withheld</option><option value="missed">missed</option>
            </select>
          </label>
          <label>Dose given (optional) <input v-model.trim="mar.adm.dose_given" placeholder="e.g., 1 tab"></label>
          <label style="grid-column:1 / -1;">Notes <input v-model.trim="mar.adm.notes" placeholder="Notes (optional)"></label>
          <label>Witness user id (optional) <input v-model.number="mar.adm.witness_user_id" type="number" min="1"></label>
          <div style="grid-column:1 / -1;display:flex;gap:8px;">
            <button class="btn btn--primary">Save</button>
            <button class="btn btn--muted" @click.prevent="mar.panel=null">Cancel</button>
            <span class="muted" v-if="mar.msg">{{ mar.msg }}</span>
          </div>
        </form>
      </div>
    </section>

    <!-- ============ Alerts ============ -->
    <section v-show="tab==='alerts'" style="margin-top:14px;">
      <form @submit.prevent="alerts_load" class="card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <label>State
          <select v-model="alerts.state">
            <option value="">(any)</option><option>open</option><option>resolved</option>
          </select>
        </label>
        <label>Type
          <select v-model="alerts.type">
            <option value="">(any)</option>
            <option value="overdue">overdue</option>
            <option value="allergy">allergy</option>
            <option value="prn_limit">prn_limit</option>
          </select>
        </label>
        <label>Resident ID <input v-model.number="alerts.resident_id" type="number" min="1" placeholder="optional"></label>
        <button class="btn btn--primary">Filter</button>
      </form>

      <div v-if="alerts.err" class="card" style="border-color:#f5c2c7;color:#b00020;">{{ alerts.err }}</div>

      <div class="card" style="overflow:auto;">
        <table class="table">
          <thead><tr><th>ID</th><th>Type</th><th>Message</th><th>Resident</th><th>Due at</th><th>State</th><th>Action</th></tr></thead>
          <tbody>
            <tr v-for="a in alerts.rows" :key="a.id">
              <td class="num">{{ a.id }}</td><td>{{ a.type }}</td><td>{{ a.message }}</td>
              <td>{{ a.resident_name || ('#'+a.resident_user_id) }}</td>
              <td>{{ a.due_at || '' }}</td><td>{{ a.state }}</td>
              <td><button class="btn btn--secondary" @click="alerts_toggle(a)">{{ a.state==='open' ? 'Resolve' : 'Reopen' }}</button></td>
            </tr>
            <tr v-if="alerts.rows.length===0"><td colspan="7" class="muted">No alerts.</td></tr>
          </tbody>
        </table>
      </div>
    </section>

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
  data:function(){
    var r=todayRange();
    return {
      tab:'rx',                 // default tab
      residents:[], residentId:null,
      meId:null,

      // Medications
      meds:{ q:'', form:'', strength:'', limit:50, page:1, rows:[], err:'' },

      // Prescriptions
      rx:{
        rows:[], err:'', msg:'',
        medSearch:'', medResults:[],
        selectedMed:null,
        form:{ medication_id:null, dose:'', route:'', prn:false, frequency:'', start_date:'', end_date:'', max_daily_dose:'', instructions:'', prescriber:'' },
        timesText:''
      },

      // MAR
      mar:{ from:r.from, to:r.to, rows:[], err:'', msg:'', panel:null,
            adm:{ outcome:'given', dose_given:'', notes:'', witness_user_id:null } },

      // Alerts
      alerts:{ state:'open', type:'', resident_id:null, rows:[], err:'' }
    };
  },

  computed:{
    rxPickedMedLabel:function(){
      var m=this.rx.selectedMed;
      return m ? (m.generic_name+' • '+m.form+' '+m.strength) : '';
    }
  },

  watch:{
    residentId:function(){
      if(this.tab==='rx') this.rx_load();
      if(this.tab==='mar') this.mar_load();
    },
    'rx.medSearch': function(val){
      var self=this;
      if(!val || val.length<2){ self.rx.medResults=[]; return; }
      apiGet('/medications',{q:val, limit:25})
        .then(function(r){ var d=r.data&&r.data.data?r.data.data:r.data; self.rx.medResults=(d&&d.items)?d.items:[]; })
        .catch(function(){ self.rx.medResults=[]; });
    }
  },

  methods:{
    /* ---------- boot ---------- */
    boot: async function(){
      await window.apiGetCsrf();
      try{
        var rr=await apiGet('/residents'); var dd=rr.data&&rr.data.data?rr.data.data:rr.data;
        this.residents = dd.items?dd.items:dd;
        if(this.residents.length){ this.residentId=this.residents[0].id; }
      }catch(e){}
      try{
        var me=await apiGet('/me'); var md=me.data&&me.data.data?me.data.data:me.data;
        this.meId = md && md.user ? md.user.id : null;
      }catch(e){}
      // initial loads
      this.rx_load(); this.mar_load(); this.meds_load(); this.alerts_load();
    },

    reloadCurrent:function(){
      if(this.tab==='meds') this.meds_load();
      if(this.tab==='rx')   this.rx_load();
      if(this.tab==='mar')  this.mar_load();
      if(this.tab==='alerts') this.alerts_load();
    },

    /* ---------- Medications ---------- */
    meds_load: async function(){
      try{
        this.meds.err='';
        var p={ q:this.meds.q, form:this.meds.form, strength:this.meds.strength, limit:this.meds.limit, page:this.meds.page };
        var r=await apiGet('/medications', p);
        var d=r.data&&r.data.data?r.data.data:r.data;
        this.meds.rows=(d&&d.items)?d.items:[];
      }catch(e){ this.meds.err=this.msgFrom(e); }
    },

    /* ---------- Prescriptions ---------- */
    rx_load: async function(){
      if(!this.residentId) return;
      try{
        this.rx.err='';
        var r=await apiGet('/residents/'+this.residentId+'/prescriptions');
        var d=r.data&&r.data.data?r.data.data:r.data;
        this.rx.rows=d.items?d.items:[];
      }catch(e){ this.rx.err=this.msgFrom(e); }
    },
    rx_pickMed:function(m){
      this.rx.form.medication_id=m.id;
      this.rx.selectedMed=m;
      this.rx.medResults=[];
      this.rx.medSearch=m.generic_name+' '+(m.brand_name||'');
    },
    rx_create: async function(){
      try{
        this.rx.msg=''; this.rx.err='';
        var body={
          medication_id:this.rx.form.medication_id,
          dose:this.rx.form.dose, route:this.rx.form.route||null,
          prn:this.rx.form.prn?1:0, frequency:this.rx.form.frequency,
          start_date:this.rx.form.start_date, end_date:this.rx.form.end_date||null,
          times:this.rx.timesText?this.rx.timesText.split(',').map(function(s){return s.trim();}):[],
          max_daily_dose:this.rx.form.max_daily_dose||null,
          instructions:this.rx.form.instructions||null,
          prescriber:this.rx.form.prescriber||null
        };
        await apiPost('/residents/'+this.residentId+'/prescriptions', body);
        this.rx.msg='Created.';
        this.rx.form={ medication_id:null, dose:'', route:'', prn:false, frequency:'', start_date:'', end_date:'', max_daily_dose:'', instructions:'', prescriber:'' };
        this.rx.selectedMed=null;
        this.rx.timesText='';
        await this.rx_load();
      }catch(e){ this.rx.err=this.msgFrom(e); }
    },

    /* ---------- MAR ---------- */
    mar_load: async function(){
      if(!this.residentId) return;
      try{
        this.mar.err='';
        var r=await apiGet('/residents/'+this.residentId+'/med-due',{from:this.mar.from,to:this.mar.to});
        var d=r.data&&r.data.data?r.data.data:r.data;
        this.mar.rows=(d&&d.items)?d.items:[];
      }catch(e){ this.mar.err=this.msgFrom(e); }
    },
    mar_open:function(it){ this.mar.msg=''; this.mar.adm={ outcome:'given', dose_given:'', notes:'', witness_user_id:null }; this.mar.panel=it; },
    mar_submit: async function(){
      try{
        var body={
          outcome:this.mar.adm.outcome,
          dose_given:this.mar.adm.dose_given||null,
          notes:this.mar.adm.notes||null,
          staff_user_id:this.meId||0,
          witness_user_id:this.mar.adm.witness_user_id||null
        };
        await apiPost('/med-schedule/'+this.mar.panel.schedule_id+'/administer', body);
        this.mar.msg='Saved.'; this.mar.panel=null; await this.mar_load();
      }catch(e){ this.mar.msg=this.msgFrom(e); }
    },

    /* ---------- Alerts ---------- */
    alerts_load: async function(){
      try{
        this.alerts.err='';
        var p={}; if(this.alerts.state) p.state=this.alerts.state; if(this.alerts.type) p.type=this.alerts.type;
        if(this.alerts.resident_id) p.resident_id=this.alerts.resident_id;
        var r=await apiGet('/med-alerts', p);
        var d=r.data&&r.data.data?r.data.data:r.data;
        this.alerts.rows=(d&&d.items)?d.items:[];
      }catch(e){ this.alerts.err=this.msgFrom(e); }
    },
    alerts_toggle: async function(a){
      try{
        var newState=a.state==='open'?'resolved':'open';
        await apiPatch('/med-alerts/'+a.id,{state:newState,resolved_by:null});
        a.state=newState;
      }catch(e){ alert(this.msgFrom(e)); }
    },

    /* ---------- misc ---------- */
    msgFrom:function(e){
      return (e&&e.response&&e.response.data&&e.response.data.error&&e.response.data.error.message)
        ? e.response.data.error.message : (e&&e.message?e.message:'Error');
    }
  },

  mounted:function(){ this.boot(); }
}).mount('#med-console');
</script>
<?php renderFooter(); ?>
