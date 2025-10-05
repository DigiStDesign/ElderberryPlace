<?php
require_once __DIR__ . '/includes/layout.php';
renderHeader('Prescriptions');
?>
<main class="container" style="max-width:1100px;margin:20px auto;">
  <div id="app-rx">
    <h2>Prescriptions</h2>

    <!-- Pick resident (from URL ?resident_id= or dropdown) -->
    <div class="card" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <label style="display:flex;gap:6px;align-items:center;">
        <span>Resident:</span>
        <select v-model.number="residentId" @change="reload" style="min-width:240px;">
          <option v-for="r in residents" :value="r.id">{{ r.full_name }} ({{ r.username }})</option>
        </select>
      </label>
      <span class="muted" v-if="!residentId">Choose a resident to view prescriptions.</span>
    </div>

    <div v-if="err" class="card" style="border-color:#f5c2c7;color:#b00020;">{{ err }}</div>

    <div v-if="residentId">
      <!-- Rx list -->
      <h3 style="margin-top:18px;">Existing prescriptions</h3>
      <div class="card" style="overflow:auto;">
        <table class="table">
          <thead>
            <tr>
              <th>Medication</th>
              <th>Dose</th>
              <th>Route</th>
              <th>PRN</th>
              <th>Freq</th>
              <th>Start</th>
              <th>End</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="r.id">
              <td>
                <strong>{{ r.generic_name }}</strong>
                <div class="muted">{{ r.brand_name }} • {{ r.form }} {{ r.strength }}</div>
              </td>
              <td>{{ r.dose }}</td>
              <td>{{ r.route || '-' }}</td>
              <td>{{ r.prn ? 'Yes':'No' }}</td>
              <td>{{ r.frequency }}</td>
              <td>{{ r.start_date }}</td>
              <td>{{ r.end_date || '' }}</td>
              <td>{{ r.status }}</td>
            </tr>
            <tr v-if="rows.length===0"><td colspan="8" class="muted">No prescriptions.</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Create form -->
      <h3 style="margin-top:18px;">New prescription</h3>
      <div class="card">
        <form @submit.prevent="create" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <!-- Med search / choose -->
          <div style="grid-column:1 / -1;">
            <label style="display:flex;gap:8px;align-items:center;">
              <input v-model.trim="medSearch" placeholder="Search medication to link… (type then click a result)" style="flex:1">
              <span v-if="form.medication_id" class="muted">Selected: {{ pickedMedLabel }}</span>
            </label>
            <div v-if="medResults.length" class="card" style="margin-top:8px;max-height:210px;overflow:auto;">
              <table class="table">
                <thead><tr><th></th><th>Generic</th><th>Brand</th><th>Form</th><th>Strength</th></tr></thead>
                <tbody>
                  <tr v-for="m in medResults" :key="m.id">
                    <td><button class="btn btn--secondary" @click.prevent="pickMed(m)">Pick</button></td>
                    <td>{{ m.generic_name }}</td><td>{{ m.brand_name }}</td><td>{{ m.form }}</td><td>{{ m.strength }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <input v-model.trim="form.dose" placeholder="Dose (e.g., 1 tab)" required>
          <input v-model.trim="form.route" placeholder="Route (e.g., oral)">

          <input v-model.trim="form.frequency" placeholder="Frequency (e.g., daily)" required>
          <label><input type="checkbox" v-model="form.prn"> PRN</label>

          <input v-model.trim="form.start_date" placeholder="Start date YYYY-MM-DD" required>
          <input v-model.trim="form.end_date" placeholder="End date (optional) YYYY-MM-DD">

          <input v-model.trim="timesText" placeholder="Times (comma HH:mm, e.g., 08:00,20:00)" style="grid-column:1 / -1">

          <input v-model.trim="form.max_daily_dose" placeholder="Max daily dose (optional)">
          <input v-model.trim="form.prescriber" placeholder="Prescriber (optional)">
          <input v-model.trim="form.instructions" placeholder="Instructions (optional)" style="grid-column:1 / -1">

          <div style="grid-column:1 / -1;display:flex;gap:8px;">
            <button class="btn btn--primary" :disabled="!form.medication_id">Create</button>
            <span class="muted" v-if="msg">{{ msg }}</span>
          </div>
        </form>
      </div>
    </div>
  </div>
</main>

<script src="https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.7/dist/axios.min.js"></script>
<script src="assets/app.js"></script>
<script>
function getQS(name){ var m=location.search.match(new RegExp('[?&]'+name+'=([^&]+)')); return m?decodeURIComponent(m[1]):''; }

Vue.createApp({
  data(){
    return {
      residents:[], residentId: Number(getQS('resident_id'))||null,
      rows:[], err:'', msg:'',
      medSearch:'', medResults:[],
      form:{ medication_id:null, dose:'', route:'', prn:false, frequency:'', start_date:'', end_date:'', max_daily_dose:'', instructions:'', prescriber:'' },
      timesText:''
    };
  },
  computed:{
    pickedMedLabel(){
      var m = (this.medResults.find(x=>x.id===this.form.medication_id)) || null;
      return m ? (m.generic_name+' • '+m.form+' '+m.strength) : ('#'+this.form.medication_id);
    }
  },
  watch:{
    medSearch(val){
      var self=this;
      if (!val || val.length<2){ self.medResults=[]; return; }
      apiGet('/medications',{q:val, limit:25}).then(function(r){
        var d=r.data&&r.data.data?r.data.data:r.data; self.medResults=(d&&d.items)?d.items:[];
      }).catch(function(){ self.medResults=[]; });
    }
  },
  methods:{
    async reload(){ this.msg=''; this.err=''; if(!this.residentId) return; await this.loadRx(); },
    async loadResidents(){
      var r=await apiGet('/residents'); var d=r.data&&r.data.data?r.data.data:r.data;
      this.residents = d.items?d.items:d;
      if(!this.residentId && this.residents.length) this.residentId=this.residents[0].id, this.reload();
    },
    async loadRx(){
      try{
        var r=await apiGet('/residents/'+this.residentId+'/prescriptions');
        var d=r.data&&r.data.data?r.data.data:r.data;
        this.rows = d.items?d.items:[];
      }catch(e){ this.err=this.msgFrom(e); }
    },
    pickMed(m){ this.form.medication_id=m.id; this.medResults=[]; this.medSearch=m.generic_name+' '+(m.brand_name||''); },
    async create(){
      try{
        this.msg=''; this.err='';
        var body={
          medication_id:this.form.medication_id,
          dose:this.form.dose, route:this.form.route||null,
          prn:this.form.prn?1:0, frequency:this.form.frequency,
          start_date:this.form.start_date, end_date:this.form.end_date||null,
          times:this.timesText?this.timesText.split(',').map(function(s){return s.trim();}):[],
          max_daily_dose:this.form.max_daily_dose||null,
          instructions:this.form.instructions||null,
          prescriber:this.form.prescriber||null
        };
        await apiPost('/residents/'+this.residentId+'/prescriptions', body);
        this.msg='Created.';
        this.form={ medication_id:null, dose:'', route:'', prn:false, frequency:'', start_date:'', end_date:'', max_daily_dose:'', instructions:'', prescriber:'' };
        this.timesText=''; await this.loadRx();
      }catch(e){ this.err=this.msgFrom(e); }
    },
    msgFrom(e){
      return (e&&e.response&&e.response.data&&e.response.data.error&&e.response.data.error.message)?e.response.data.error.message:(e&&e.message?e.message:'Error');
    }
  },
  mounted(){ this.loadResidents(); }
}).mount('#app-rx');
</script>
<?php renderFooter(); ?>
