<?php
// medications.php
// UI for listing + creating medications (PHP 5 compatible)

require_once __DIR__ . '/includes/layout.php';
renderHeader('Medications');
?>
<main class="container" style="max-width:1000px;margin:20px auto;">
  <div id="app-meds">
    <h2>Medication Console</h2>

    <!-- Search -->
    <form @submit.prevent="load" class="card" style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0;padding:12px;">
      <input v-model.trim="q" placeholder="Search name… (blank = all)" style="flex:1;min-width:220px;">
      <input v-model.trim="formFilter" placeholder="Form (e.g., tablet)" style="min-width:160px;">
      <input v-model.trim="strength" placeholder="Strength (e.g., 500 mg)" style="min-width:160px;">
      <input v-model.number="limit" type="number" min="1" max="100" title="limit" style="width:92px">
      <button class="btn btn--primary" type="submit">Search</button>
    </form>

    <div v-if="err" class="card" style="border-color:#f5c2c7;color:#b00020;">{{ err }}</div>

    <!-- Results -->
    <div class="card" style="overflow:auto;">
      <table class="table">
        <thead>
          <tr>
            <th style="width:70px;">ID</th>
            <th>Generic</th>
            <th>Brand</th>
            <th>Form</th>
            <th>Strength</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="m in rows" :key="m.id">
            <td class="num">{{ m.id }}</td>
            <td>{{ m.generic_name }}</td>
            <td>{{ m.brand_name }}</td>
            <td>{{ m.form }}</td>
            <td>{{ m.strength }}</td>
          </tr>
          <tr v-if="rows.length===0">
            <td colspan="5" class="muted">No results.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pager -->
    <div style="display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:8px;">
      <button class="btn btn--secondary" :disabled="page<=1" @click="goPrev">Prev</button>
      <span>Page {{ page }}</span>
      <button class="btn btn--secondary" :disabled="rows.length<limit" @click="goNext">Next</button>
    </div>

    <!-- Add new medication -->
    <h3 style="margin:20px 0 8px;">Add medication</h3>
    <div class="card">
      <form @submit.prevent="create" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <input v-model.trim="newMed.generic_name"
               placeholder="Generic name (e.g., Paracetamol)" required
               style="grid-column:1 / -1">

        <input v-model.trim="newMed.brand_name"
               placeholder="Brand (optional, e.g., Panadol)">

        <div style="display:flex;gap:10px;">
          <input v-model.trim="newMed.form"
                 placeholder="Form (e.g., tablet)" required style="flex:1">
          <input v-model.trim="newMed.strength"
                 placeholder="Strength (e.g., 500 mg)" required style="flex:1">
        </div>

        <div style="grid-column:1 / -1;display:flex;gap:8px;align-items:center;">
          <button class="btn btn--primary" :disabled="creating">Create</button>
          <button class="btn" type="button" :disabled="creating" @click="resetNewMed">Cancel</button>
          <span class="muted" v-if="msg">{{ msg }}</span>
          <span style="color:#b00020" v-if="errAdd">{{ errAdd }}</span>
        </div>
      </form>
    </div>
  </div>
</main>

<!-- Libraries -->
<script src="https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.7/dist/axios.min.js"></script>
<script src="assets/app.js"></script>
<script>
// medications app
Vue.createApp({
  data: function(){
    return {
      // list/search state
      q: '', formFilter: '', strength: '', limit: 50, page: 1,
      rows: [], err: '',

      // create form state
      newMed: { generic_name:'', brand_name:'', form:'', strength:'' },
      creating: false, msg: '', errAdd: ''
    };
  },

  methods: {
    // ------- Helpers -------
    msgFrom: function(e){
      return (e && e.response && e.response.data && e.response.data.error && e.response.data.error.message)
        ? e.response.data.error.message
        : (e && e.message ? e.message : 'Error');
    },
    isValidNewMed: function(){
      return !!(this.newMed.generic_name && this.newMed.form && this.newMed.strength);
    },
    payloadFromNewMed: function(){
      return {
        generic_name: this.newMed.generic_name,
        brand_name:   this.newMed.brand_name || null,
        form:         this.newMed.form,
        strength:     this.newMed.strength
      };
    },

    // ------- Paging -------
    goPrev: function(){
      if (this.page > 1) { this.page--; this.load(); }
    },
    goNext: function(){
      if (this.rows.length >= this.limit) { this.page++; this.load(); }
    },

    // ------- Actions -------
    load: async function(){
      try{
        this.err = '';
        var p = { q:this.q, form:this.formFilter, strength:this.strength, limit:this.limit, page:this.page };
        var r = await apiGet('/medications', p);
        // handle both {data:{items:[]}} and {items:[]}
        var envelope = (r.data && r.data.data) ? r.data.data : r.data;
        this.rows = (envelope && envelope.items) ? envelope.items : [];
      }catch(e){
        this.err = this.msgFrom(e);
      }
    },

    create: async function(){
      this.msg = ''; this.errAdd = '';
      if (!this.isValidNewMed()){
        this.errAdd = 'generic_name, form and strength are required.'; return;
      }

      try{
        this.creating = true;
        await apiPost('/medications', this.payloadFromNewMed());
        this.msg = 'Created.';
        this.resetNewMed();
        await this.load();
      }catch(e){
        var m = this.msgFrom(e);
        if ((m||'').toLowerCase().match(/duplicate|exists|409/)){
          m = 'Medication already exists (generic + form + strength must be unique).';
        }
        this.errAdd = m;
      }finally{
        this.creating = false;
      }
    },

    resetNewMed: function(){
      this.newMed = { generic_name:'', brand_name:'', form:'', strength:'' };
      this.errAdd = ''; this.msg = '';
    }
  },

  mounted: function(){
    // Ensure CSRF header is set for this tab if your helper provides it
    if (window.apiGetCsrf) { window.apiGetCsrf().catch(function(){}); }
    this.load();
  }
}).mount('#app-meds');
</script>
<?php renderFooter(); ?>
