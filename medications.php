<?php
require_once __DIR__ . '/includes/layout.php';
renderHeader('Medications');
?>
<main class="container" style="max-width:1000px;margin:20px auto;">
  <div id="app-meds">
    <h2>Medications</h2>

    <form @submit.prevent="load" style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0;">
      <input v-model.trim="q" placeholder="Search name…" style="flex:1;min-width:220px;">
      <input v-model.trim="form" placeholder="Form (e.g., tablet)">
      <input v-model.trim="strength" placeholder="Strength (e.g., 500 mg)">
      <input v-model.number="limit" type="number" min="1" max="100" title="limit" style="width:92px">
      <button class="btn btn--primary" type="submit">Search</button>
    </form>

    <div v-if="err" class="card" style="border-color:#f5c2c7;color:#b00020;">{{ err }}</div>

    <div class="card" style="overflow:auto;">
      <table class="table">
        <thead>
          <tr><th>ID</th><th>Generic</th><th>Brand</th><th>Form</th><th>Strength</th></tr>
        </thead>
        <tbody>
          <tr v-for="m in rows" :key="m.id">
            <td class="num">{{ m.id }}</td>
            <td>{{ m.generic_name }}</td>
            <td>{{ m.brand_name }}</td>
            <td>{{ m.form }}</td>
            <td>{{ m.strength }}</td>
          </tr>
          <tr v-if="rows.length===0"><td colspan="5" class="muted">No results.</td></tr>
        </tbody>
      </table>
    </div>

    <div style="display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:8px;">
      <button class="btn btn--secondary" :disabled="page<=1" @click="page--; load()">Prev</button>
      <span>Page {{ page }}</span>
      <button class="btn btn--secondary" :disabled="rows.length<limit" @click="page++; load()">Next</button>
    </div>
  </div>
</main>

<script src="https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.7/dist/axios.min.js"></script>
<script src="assets/app.js"></script>
<script>
Vue.createApp({
  data(){ return { q:'', form:'', strength:'', limit:50, page:1, rows:[], err:'' }; },
  methods:{
    async load(){
      try{
        this.err='';
        var p = { q:this.q, form:this.form, strength:this.strength, limit:this.limit, page:this.page };
        var r = await apiGet('/medications', p);
        var data = r.data && r.data.data ? r.data.data : r.data;
        this.rows = (data && data.items) ? data.items : [];
      }catch(e){ this.err=this.msgFrom(e); }
    },
    msgFrom(e){
      return (e&&e.response&&e.response.data&&e.response.data.error&&e.response.data.error.message) ? e.response.data.error.message : (e&&e.message?e.message:'Error');
    }
  },
  mounted(){ this.load(); }
}).mount('#app-meds');
</script>
<?php renderFooter(); ?>
