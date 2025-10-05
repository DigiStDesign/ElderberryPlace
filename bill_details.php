<?php
require_once __DIR__ . '/includes/layout.php';
renderHeader('Bill Details');
?>
<main style="max-width:900px;margin:24px auto;padding:16px;">
  <div id="app">
    <div v-if="msg" style="background:#ecfdf3;border:1px solid #badbcc;padding:10px;border-radius:6px;margin-bottom:12px;">{{ msg }}</div>
    <div v-if="err" style="background:#fdecea;border:1px solid #f5c2c7;padding:10px;border-radius:6px;margin-bottom:12px;color:#b00020;">{{ err }}</div>

    <div v-if="loading">Loading…</div>

    <template v-else-if="bill">
      <!-- Header -->
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <div>
          <h2 style="margin:0;">Bill #{{ bill.bill_number }}</h2>
          <p style="margin:4px 0 0;color:#666;">{{ bill.resident_name }}</p>
        </div>
        <div style="text-align:right;">
          <span :style="statusBadge(bill.status)">{{ bill.status.toUpperCase() }}</span>
          <div style="margin-top:8px;">
            <button @click="exportPDF" style="padding:8px 16px;border:none;border-radius:6px;background:#dc3545;color:#fff;cursor:pointer;margin-right:8px;">Export PDF</button>
            <a href="billing.php" style="padding:8px 16px;border:1px solid #ddd;border-radius:6px;background:#fff;text-decoration:none;color:#333;">Back to List</a>
          </div>
        </div>
      </div>

      <!-- Bill Information -->
      <section style="background:#f9fafb;border:1px solid #e5e7eb;padding:16px;border-radius:8px;margin-bottom:16px;">
        <h3 style="margin:0 0 12px;">Bill Information</h3>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">
          <div><strong>Bill Date:</strong> {{ formatDate(bill.bill_date) }}</div>
          <div><strong>Due Date:</strong> {{ formatDate(bill.due_date) || 'Not set' }}</div>
          <div><strong>Period:</strong> {{ formatPeriod(bill.period_start, bill.period_end) }}</div>
          <div><strong>Room:</strong> {{ bill.room_number || 'N/A' }}</div>
        </div>
        <div v-if="bill.notes" style="margin-top:12px;">
          <strong>Notes:</strong> {{ bill.notes }}
        </div>
      </section>

      <!-- Line Items -->
      <section style="background:#fff;border:1px solid #e5e7eb;padding:16px;border-radius:8px;margin-bottom:16px;">
        <h3 style="margin:0 0 12px;">Items</h3>
        <table style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="background:#f8f8f8;border-bottom:1px solid #ddd;">
              <th style="text-align:left;padding:8px;">Description</th>
              <th style="text-align:center;padding:8px;width:100px;">Qty</th>
              <th style="text-align:right;padding:8px;width:100px;">Unit Price</th>
              <th style="text-align:right;padding:8px;width:120px;">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in bill.items" :key="item.id" style="border-bottom:1px solid #eee;">
              <td style="padding:8px;">
                {{ item.description }}
                <span style="font-size:11px;color:#666;margin-left:8px;">({{ item.item_type }})</span>
              </td>
              <td style="padding:8px;text-align:center;">{{ parseFloat(item.quantity).toFixed(2) }}</td>
              <td style="padding:8px;text-align:right;">${{ parseFloat(item.unit_price).toFixed(2) }}</td>
              <td style="padding:8px;text-align:right;font-weight:bold;">${{ parseFloat(item.total_price).toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- Totals -->
      <section style="background:#fff;border:1px solid #e5e7eb;padding:16px;border-radius:8px;margin-bottom:16px;">
        <div style="display:flex;justify-content:flex-end;">
          <div style="width:300px;">
            <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee;">
              <span>Subtotal:</span>
              <span>${{ (parseFloat(bill.subtotal_medications) + parseFloat(bill.subtotal_services)).toFixed(2) }}</span>
            </div>
            <div v-if="parseFloat(bill.tax_amount) > 0" style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee;">
              <span>Tax (GST):</span>
              <span>${{ parseFloat(bill.tax_amount).toFixed(2) }}</span>
            </div>
            <div v-if="parseFloat(bill.discount_amount) > 0" style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #eee;">
              <span>Discount:</span>
              <span>-${{ parseFloat(bill.discount_amount).toFixed(2) }}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:12px 0;font-size:18px;font-weight:bold;background:#f8f8f8;padding:12px;margin-top:8px;border-radius:6px;">
              <span>Grand Total:</span>
              <span>${{ parseFloat(bill.grand_total).toFixed(2) }}</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Payment History -->
      <section style="background:#fff;border:1px solid #e5e7eb;padding:16px;border-radius:8px;margin-bottom:16px;">
        <h3 style="margin:0 0 12px;">Payment History</h3>
        
        <div v-if="bill.receipts.length === 0" style="background:#f8f9fa;padding:12px;border-radius:6px;color:#666;">
          No payments recorded yet.
        </div>
        
        <table v-else style="width:100%;border-collapse:collapse;">
          <thead>
            <tr style="background:#f8f8f8;border-bottom:1px solid #ddd;">
              <th style="text-align:left;padding:8px;">Receipt #</th>
              <th style="text-align:left;padding:8px;">Date</th>
              <th style="text-align:left;padding:8px;">Method</th>
              <th style="text-align:right;padding:8px;">Amount</th>
              <th style="text-align:center;padding:8px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in bill.receipts" :key="r.id" style="border-bottom:1px solid #eee;">
              <td style="padding:8px;">{{ r.receipt_number }}</td>
              <td style="padding:8px;">{{ formatDate(r.payment_date) }}</td>
              <td style="padding:8px;">{{ formatPaymentMethod(r.payment_method) }}</td>
              <td style="padding:8px;text-align:right;font-weight:bold;color:#198754;">${{ parseFloat(r.amount_paid).toFixed(2) }}</td>
              <td style="padding:8px;text-align:center;">
                <button @click="viewReceipt(r.id)" style="padding:4px 8px;border:none;border-radius:4px;background:#6c757d;color:#fff;cursor:pointer;">View</button>
              </td>
            </tr>
          </tbody>
        </table>
        
        <div style="margin-top:16px;padding:12px;background:#e7f3ff;border-radius:6px;">
          <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
            <strong>Amount Paid:</strong>
            <span style="color:#198754;font-weight:bold;">${{ parseFloat(bill.amount_paid).toFixed(2) }}</span>
          </div>
          <div style="display:flex;justify-content:space-between;">
            <strong>Balance Due:</strong>
            <span :style="'font-weight:bold;font-size:18px;color:' + (parseFloat(bill.balance) > 0 ? '#dc3545' : '#198754')">${{ parseFloat(bill.balance).toFixed(2) }}</span>
          </div>
        </div>
        
        <div v-if="parseFloat(bill.balance) > 0 && bill.status !== 'cancelled'" style="margin-top:12px;">
          <button @click="recordPayment" style="padding:10px 20px;border:none;border-radius:6px;background:#198754;color:#fff;cursor:pointer;width:100%;">Record Payment</button>
        </div>
      </section>

      <!-- Payment Modal -->
      <div v-if="showPaymentModal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:1000;">
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
                <input type="number" step="0.01" min="0.01" :max="parseFloat(bill.balance)" v-model.number="paymentForm.amount_paid" required style="width:100%;">
              </label>
              <small style="color:#666;">Balance due: ${{ parseFloat(bill.balance).toFixed(2) }}</small>
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
              <button type="button" @click="showPaymentModal=false" style="padding:8px 16px;border:1px solid #ddd;border-radius:6px;background:#fff;cursor:pointer;">Cancel</button>
              <button type="submit" style="padding:8px 16px;border:none;border-radius:6px;background:#198754;color:#fff;cursor:pointer;">Record Payment</button>
            </div>
          </form>
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
      bill: null,
      err: '',
      msg: '',
      showPaymentModal: false,
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
      const params = new URLSearchParams(window.location.search);
      const billId = params.get('id');
      if (!billId) {
        this.err = 'No bill ID provided';
        this.loading = false;
        return;
      }
      await this.loadBill(billId);
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
    async loadBill(billId) {
      const r = await apiGet('/billing/bills/' + billId);
      this.bill = r.data.data;
    },
    exportPDF() {
      window.open(API_BASE + '/index.php?r=/v1/billing/bills/' + this.bill.id + '/pdf&download=1', '_blank');
    },
    viewReceipt(receiptId) {
      window.location = 'receipt_details.php?id=' + receiptId;
    },
    recordPayment() {
      this.paymentForm = {
        bill_id: this.bill.id,
        payment_date: new Date().toISOString().split('T')[0],
        payment_method: '',
        amount_paid: parseFloat(this.bill.balance),
        reference_number: '',
        notes: ''
      };
      this.showPaymentModal = true;
    },
    async submitPayment() {
      try {
        this.err = '';
        this.msg = '';
        await apiPost('/billing/receipts', this.paymentForm);
        this.msg = 'Payment recorded successfully';
        this.showPaymentModal = false;
        await this.loadBill(this.bill.id);
      } catch (e) {
        this.err = this.pickError(e);
      }
    },
    formatDate(date) {
      if (!date) return '';
      return new Date(date).toLocaleDateString('en-AU');
    },
    formatPeriod(start, end) {
      if (!start || !end) return 'N/A';
      return this.formatDate(start) + ' - ' + this.formatDate(end);
    },
    formatPaymentMethod(method) {
      return method.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
    },
    statusBadge(status) {
      const colors = {
        draft: 'background:#fff3cd;color:#856404;',
        issued: 'background:#cfe2ff;color:#084298;',
        paid: 'background:#d1e7dd;color:#0f5132;',
        cancelled: 'background:#f8d7da;color:#842029;'
      };
      return 'padding:6px 14px;border-radius:14px;font-size:12px;font-weight:bold;display:inline-block;' + (colors[status] || '');
    }
  }
}).mount('#app');
</script>
<?php renderFooter(); ?>