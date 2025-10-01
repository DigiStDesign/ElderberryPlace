<?php
require_once __DIR__ . '/includes/layout.php';
renderHeader('Billing Management');
?>
<main style="max-width:1200px;margin:24px auto;padding:16px;">
  <div id="app">
    <!-- Messages -->
    <div v-if="msg" style="background:#ecfdf3;border:1px solid #badbcc;padding:10px;border-radius:6px;margin-bottom:12px;">{{ msg }}</div>
    <div v-if="err" style="background:#fdecea;border:1px solid #f5c2c7;padding:10px;border-radius:6px;margin-bottom:12px;color:#b00020;">{{ err }}</div>

    <div v-if="loading">Loading…</div>

    <template v-else>
      <!-- Access control -->
      <div v-if="!me || (me.role!=='ADMIN' && me.role!=='STAFF')">
        <p>Admin/Staff only. <a href="index.php">Back to home</a></p>
      </div>

      <div v-else>
        <h2 style="margin:0 0 12px;">Billing Management</h2>

        <!-- Filters -->
        <section style="background:#f9fafb;border:1px solid #e5e7eb;padding:12px;border-radius:8px;margin-bottom:16px;">
          <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <div>
              <label>Resident<br>
                <select v-model.number="filters.resident_user_id" style="min-width:200px;">
                  <option :value="0">All residents</option>
                  <option v-for="r in residents" :key="r.id" :value="r.id">{{ r.full_name }}</option>
                </select>
              </label>
            </div>
            <div>
              <label>Status<br>
                <select v-model="filters.status">
                  <option value="">All statuses</option>
                  <option value="draft">Draft</option>
                  <option value="issued">Issued</option>
                  <option value="paid">Paid</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </label>
            </div>
            <div>
              <label>From Date<br><input type="date" v-model="filters.from_date"></label>
            </div>
            <div>
              <label>To Date<br><input type="date" v-model="filters.to_date"></label>
            </div>
            <div>
              <button @click="loadBills" style="padding:8px 16px;border:none;border-radius:6px;background:#2b6cb0;color:#fff;cursor:pointer;">Filter</button>
            </div>
            <div style="margin-left:auto;">
              <button @click="showCreateBillModal" style="padding:8px 16px;border:none;border-radius:6px;background:#0d6efd;color:#fff;cursor:pointer;">+ Create Bill</button>
            </div>
          </div>
        </section>

        <!-- Bills list -->
        <div v-if="bills.length === 0" style="background:#eef2ff;border:1px solid #c7d2fe;padding:12px;border-radius:8px;">
          No bills found matching the filters.
        </div>

        <div v-else style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8f8f8;border-bottom:1px solid #ddd;">
                <th style="text-align:left;padding:8px;">Bill Number</th>
                <th style="text-align:left;padding:8px;">Resident</th>
                <th style="text-align:left;padding:8px;">Date</th>
                <th style="text-align:left;padding:8px;">Due Date</th>
                <th style="text-align:right;padding:8px;">Total</th>
                <th style="text-align:center;padding:8px;">Status</th>
                <th style="text-align:center;padding:8px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="b in bills" :key="b.id" style="border-bottom:1px solid #eee;">
                <td style="padding:8px;">{{ b.bill_number }}</td>
                <td style="padding:8px;">{{ b.resident_name }}</td>
                <td style="padding:8px;">{{ formatDate(b.bill_date) }}</td>
                <td style="padding:8px;">{{ formatDate(b.due_date) || '-' }}</td>
                <td style="padding:8px;text-align:right;">${{ parseFloat(b.grand_total).toFixed(2) }}</td>
                <td style="padding:8px;text-align:center;">
                  <span :style="statusBadge(b.status)">{{ b.status.toUpperCase() }}</span>
                </td>
                <td style="padding:8px;text-align:center;">
                  <button @click="viewBill(b.id)" style="padding:4px 8px;margin:2px;border:none;border-radius:4px;background:#6c757d;color:#fff;cursor:pointer;">View</button>
                  <button @click="exportBillPDF(b.id)" style="padding:4px 8px;margin:2px;border:none;border-radius:4px;background:#dc3545;color:#fff;cursor:pointer;">PDF</button>
                  <button v-if="b.status!=='paid' && b.status!=='cancelled'" @click="recordPayment(b.id)" style="padding:4px 8px;margin:2px;border:none;border-radius:4px;background:#198754;color:#fff;cursor:pointer;">Pay</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Create Bill Modal -->
        <div v-if="modals.createBill" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:1000;">
          <div style="background:#fff;padding:24px;border-radius:8px;max-width:600px;width:90%;max-height:90vh;overflow-y:auto;">
            <h3 style="margin:0 0 16px;">Create New Bill</h3>
            
            <form @submit.prevent="createBill" style="display:grid;gap:12px;">
              <div>
                <label>Resident *<br>
                  <select v-model.number="createForm.resident_user_id" required style="width:100%;">
                    <option value="">-- Select Resident --</option>
                    <option v-for="r in residents" :key="r.id" :value="r.id">{{ r.full_name }}</option>
                  </select>
                </label>
              </div>
              
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                  <label>Period Start<br>
                    <input type="date" v-model="createForm.period_start" style="width:100%;">
                  </label>
                </div>
                <div>
                  <label>Period End<br>
                    <input type="date" v-model="createForm.period_end" style="width:100%;">
                  </label>
                </div>
              </div>
              
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                  <label>Bill Date<br>
                    <input type="date" v-model="createForm.bill_date" style="width:100%;">
                  </label>
                </div>
                <div>
                  <label>Due Date<br>
                    <input type="date" v-model="createForm.due_date" style="width:100%;">
                  </label>
                </div>
              </div>
              
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                  <label>Tax Rate (GST %)<br>
                    <input type="number" step="0.01" min="0" max="100" v-model.number="createForm.tax_rate_pct" style="width:100%;">
                  </label>
                </div>
                <div>
                  <label>Discount Amount ($)<br>
                    <input type="number" step="0.01" min="0" v-model.number="createForm.discount_amount" style="width:100%;">
                  </label>
                </div>
              </div>
              
              <div>
                <label>Status<br>
                  <select v-model="createForm.status" style="width:100%;">
                    <option value="draft">Draft</option>
                    <option value="issued">Issued</option>
                  </select>
                </label>
              </div>
              
              <div>
                <label>Notes<br>
                  <textarea v-model="createForm.notes" rows="3" style="width:100%;"></textarea>
                </label>
              </div>
              
              <div v-if="createForm.resident_user_id" style="background:#f0f9ff;border:1px solid#bfdbfe;padding:10px;border-radius:6px;">
                <strong>Preview unbilled items:</strong>
                <button type="button" @click="loadUnbilledPreview" style="margin-left:8px;padding:4px 8px;border:none;border-radius:4px;background:#3b82f6;color:#fff;cursor:pointer;">Load</button>
                <div v-if="unbilledPreview" style="margin-top:8px;font-size:14px;">
                  <div>Medications: {{ unbilledPreview.medications.length }} items</div>
                  <div>Services: {{ unbilledPreview.services.length }} items</div>
                  <div><strong>Estimated Total: ${{ unbilledPreview.totals.grand_total.toFixed(2) }}</strong></div>
                </div>
              </div>
              
              <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px;">
                <button type="button" @click="modals.createBill=false" style="padding:8px 16px;border:1px solid #ddd;border-radius:6px;background:#fff;cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:8px 16px;border:none;border-radius:6px;background:#0d6efd;color:#fff;cursor:pointer;">Create Bill</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Payment Modal -->
        <div v-if="modals.payment" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:1000;">
          <div style="background:#fff;padding:24px;border-radius:8px;max-width:500px;width:90%;">
            <h3 style="margin:0 0 16px;">Record Payment</h3>
            
            <form @submit.prevent="submitPayment" style="display:grid;gap:12px;">
              <div>
                <label>Payment Date *<br>
                  <input type="date" v-model="paymentForm.payment_date" required style="width:100%;">
                </label>
              </div>
              
              <div>
                <label>Payment Method *<br>
                  <select v-model="paymentForm.payment_method" required style="width:100%;">
                    <option value="">-- Select --</option>
                    <option value="cash">Cash</option>
                    <option value="card">Card</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="cheque">Cheque</option>
                    <option value="other">Other</option>
                  </select>
                </label>
              </div>
              
              <div>
                <label>Amount Paid * ($)<br>
                  <input type="number" step="0.01" min="0.01" v-model.number="paymentForm.amount_paid" required style="width:100%;">
                </label>
              </div>
              
              <div>
                <label>Reference Number<br>
                  <input type="text" v-model="paymentForm.reference_number" placeholder="Cheque/Transaction #" style="width:100%;">
                </label>
              </div>
              
              <div>
                <label>Notes<br>
                  <textarea v-model="paymentForm.notes" rows="2" style="width:100%;"></textarea>
                </label>
              </div>
              
              <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px;">
                <button type="button" @click="modals.payment=false" style="padding:8px 16px;border:1px solid #ddd;border-radius:6px;background:#fff;cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:8px 16px;border:none;border-radius:6px;background:#198754;color:#fff;cursor:pointer;">Record Payment</button>
              </div>
            </form>
          </div>
        </div>

      </div>
    </template>
  </div>
</main>

<script src="https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.7/dist/axios.min.js"></script>
<script src="/assets/app.js"></script>
<script>
Vue.createApp({
  data() {
    return {
      loading: true,
      me: null,
      residents: [],
      bills: [],
      unbilledPreview: null,
      err: '',
      msg: '',
      filters: {
        resident_user_id: 0,
        status: '',
        from_date: '',
        to_date: ''
      },
      modals: {
        createBill: false,
        payment: false
      },
      createForm: {
        resident_user_id: '',
        period_start: '',
        period_end: '',
        bill_date: new Date().toISOString().split('T')[0],
        due_date: '',
        tax_rate_pct: 10,
        discount_amount: 0,
        status: 'draft',
        notes: ''
      },
      paymentForm: {
        bill_id: null,
        payment_date: new Date().toISOString().split('T')[0],
        payment_method: '',
        amount_paid: 0,
        reference_number: '',
        notes: ''
      }
    };
  },
  async mounted() {
    try {
      await window.apiGetCsrf();
      await this.loadMe();
      if (!this.me || (this.me.role !== 'ADMIN' && this.me.role !== 'STAFF')) {
        this.loading = false;
        return;
      }
      await this.loadResidents();
      await this.loadBills();
    } catch (e) {
      this.err = this.pickError(e);
    } finally {
      this.loading = false;
    }
  },
  methods: {
    pickError(e) {
      return (e && e.response && e.response.data && e.response.data.error && e.response.data.error.message)
        ? e.response.data.error.message
        : (e && e.message ? e.message : 'Unexpected error');
    },
    async loadMe() {
      const r = await apiGet('/me');
      this.me = (r.data && r.data.data) ? r.data.data.user : null;
    },
    async loadResidents() {
      const r = await apiGet('/residents');
      const data = r.data && r.data.data ? r.data.data : {};
      this.residents = data.items || data || [];
    },
    async loadBills() {
      this.err = '';
      this.msg = '';
      const params = {};
      if (this.filters.resident_user_id) params.resident_user_id = this.filters.resident_user_id;
      if (this.filters.status) params.status = this.filters.status;
      if (this.filters.from_date) params.from_date = this.filters.from_date;
      if (this.filters.to_date) params.to_date = this.filters.to_date;
      
      const r = await apiGet('/billing/bills', { params });
      this.bills = (r.data && r.data.data && r.data.data.items) ? r.data.data.items : [];
    },
    showCreateBillModal() {
      this.createForm = {
        resident_user_id: this.filters.resident_user_id || '',
        period_start: '',
        period_end: '',
        bill_date: new Date().toISOString().split('T')[0],
        due_date: '',
        tax_rate_pct: 10,
        discount_amount: 0,
        status: 'draft',
        notes: ''
      };
      this.unbilledPreview = null;
      this.modals.createBill = true;
    },
    async loadUnbilledPreview() {
      if (!this.createForm.resident_user_id) return;
      try {
        const params = {};
        if (this.createForm.period_start) params.period_start = this.createForm.period_start;
        if (this.createForm.period_end) params.period_end = this.createForm.period_end;
        
        const r = await apiGet('/billing/unbilled-items/' + this.createForm.resident_user_id, { params });
        this.unbilledPreview = r.data.data;
      } catch (e) {
        this.err = this.pickError(e);
      }
    },
    async createBill() {
      try {
        this.err = '';
        this.msg = '';
        const payload = {
          resident_user_id: this.createForm.resident_user_id,
          period_start: this.createForm.period_start || null,
          period_end: this.createForm.period_end || null,
          bill_date: this.createForm.bill_date,
          due_date: this.createForm.due_date || null,
          tax_rate: this.createForm.tax_rate_pct / 100,
          discount_amount: this.createForm.discount_amount,
          status: this.createForm.status,
          notes: this.createForm.notes || null
        };
        
        await apiPost('/billing/bills', payload);
        this.msg = 'Bill created successfully';
        this.modals.createBill = false;
        await this.loadBills();
      } catch (e) {
        this.err = this.pickError(e);
      }
    },
    viewBill(bill_id) {
      window.location = 'bill_details.php?id=' + bill_id;
    },
    exportBillPDF(bill_id) {
      window.open(API_BASE + '/index.php?r=/v1/billing/bills/' + bill_id + '/pdf', '_blank');
    },
    recordPayment(bill_id) {
      this.paymentForm = {
        bill_id: bill_id,
        payment_date: new Date().toISOString().split('T')[0],
        payment_method: '',
        amount_paid: 0,
        reference_number: '',
        notes: ''
      };
      this.modals.payment = true;
    },
    async submitPayment() {
      try {
        this.err = '';
        this.msg = '';
        await apiPost('/billing/receipts', this.paymentForm);
        this.msg = 'Payment recorded successfully';
        this.modals.payment = false;
        await this.loadBills();
      } catch (e) {
        this.err = this.pickError(e);
      }
    },
    formatDate(date) {
      if (!date) return '';
      return new Date(date).toLocaleDateString('en-AU');
    },
    statusBadge(status) {
      const colors = {
        draft: 'background:#fff3cd;color:#856404;',
        issued: 'background:#cfe2ff;color:#084298;',
        paid: 'background:#d1e7dd;color:#0f5132;',
        cancelled: 'background:#f8d7da;color:#842029;'
      };
      return 'padding:4px 10px;border-radius:12px;font-size:11px;font-weight:bold;' + (colors[status] || '');
    }
  }
}).mount('#app');
</script>
<?php renderFooter(); ?>