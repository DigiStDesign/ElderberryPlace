<?php
require_once __DIR__ . '/includes/layout.php';
renderHeader('Receipt Details');
?>
<main style="max-width:700px;margin:24px auto;padding:16px;">
  <div id="app">
    <div v-if="err" style="background:#fdecea;border:1px solid #f5c2c7;padding:10px;border-radius:6px;margin-bottom:12px;color:#b00020;">{{ err }}</div>

    <div v-if="loading">Loading…</div>

    <template v-else-if="receipt">
      <!-- Header -->
      <div style="text-align:center;margin-bottom:24px;">
        <h2 style="margin:0;color:#198754;">PAYMENT RECEIPT</h2>
        <p style="margin:8px 0 0;font-size:20px;font-weight:bold;">{{ receipt.receipt_number }}</p>
      </div>

      <!-- Actions -->
      <div style="text-align:center;margin-bottom:24px;">
        <button @click="exportPDF" style="padding:10px 24px;border:none;border-radius:6px;background:#dc3545;color:#fff;cursor:pointer;margin-right:8px;">Download PDF</button>
        <a :href="'bill_details.php?id=' + receipt.bill_id" style="padding:10px 24px;border:1px solid #ddd;border-radius:6px;background:#fff;text-decoration:none;color:#333;">View Bill</a>
      </div>

      <!-- Receipt Details -->
      <section style="background:#f9fafb;border:2px solid #198754;padding:20px;border-radius:8px;margin-bottom:16px;">
        <div style="display:grid;gap:12px;">
          <div style="display:flex;justify-content:space-between;">
            <strong>Receipt Number:</strong>
            <span>{{ receipt.receipt_number }}</span>
          </div>
          <div style="display:flex;justify-content:space-between;">
            <strong>Bill Number:</strong>
            <span>{{ receipt.bill_number }}</span>
          </div>
          <div style="display:flex;justify-content:space-between;">
            <strong>Payment Date:</strong>
            <span>{{ formatDate(receipt.payment_date) }}</span>
          </div>
          <div style="display:flex;justify-content:space-between;">
            <strong>Payment Method:</strong>
            <span>{{ formatPaymentMethod(receipt.payment_method) }}</span>
          </div>
          <div v-if="receipt.reference_number" style="display:flex;justify-content:space-between;">
            <strong>Reference:</strong>
            <span>{{ receipt.reference_number }}</span>
          </div>
          <hr style="border:none;border-top:1px solid #ddd;margin:8px 0;">
          <div style="display:flex;justify-content:space-between;font-size:20px;font-weight:bold;color:#198754;">
            <strong>Amount Paid:</strong>
            <span>${{ parseFloat(receipt.amount_paid).toFixed(2) }}</span>
          </div>
        </div>
      </section>

      <!-- Recipient Details -->
      <section style="background:#fff;border:1px solid #e5e7eb;padding:16px;border-radius:8px;margin-bottom:16px;">
        <h3 style="margin:0 0 12px;">Received From</h3>
        <div style="color:#666;">
          <div style="font-size:16px;font-weight:bold;color:#000;margin-bottom:4px;">{{ receipt.resident_name }}</div>
          <div v-if="receipt.room_number">Room: {{ receipt.room_number }}</div>
          <div v-if="receipt.resident_email">Email: {{ receipt.resident_email }}</div>
        </div>
      </section>

      <!-- Notes -->
      <section v-if="receipt.notes" style="background:#fff;border:1px solid #e5e7eb;padding:16px;border-radius:8px;margin-bottom:16px;">
        <h3 style="margin:0 0 8px;">Notes</h3>
        <p style="margin:0;color:#666;">{{ receipt.notes }}</p>
      </section>

      <!-- Footer Info -->
      <div style="text-align:center;padding:16px;background:#f8f9fa;border-radius:6px;font-size:12px;color:#666;">
        <p style="margin:0;">This is an official receipt for payment received.</p>
        <p style="margin:4px 0 0;">Please retain for your records.</p>
        <p v-if="receipt.created_by_name" style="margin:8px 0 0;font-style:italic;">
          Processed by: {{ receipt.created_by_name }}
        </p>
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
      receipt: null,
      err: ''
    };
  },
  async mounted() {
    try {
      const params = new URLSearchParams(window.location.search);
      const receiptId = params.get('id');
      if (!receiptId) {
        this.err = 'No receipt ID provided';
        this.loading = false;
        return;
      }
      await this.loadReceipt(receiptId);
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
    async loadReceipt(receiptId) {
      const r = await apiGet('/billing/receipts/' + receiptId);
      this.receipt = r.data.data;
    },
    exportPDF() {
      window.open(API_BASE + '/index.php?r=/v1/billing/receipts/' + this.receipt.id + '/pdf&download=1', '_blank');
    },
    formatDate(date) {
      if (!date) return '';
      return new Date(date).toLocaleDateString('en-AU', { year: 'numeric', month: 'long', day: 'numeric' });
    },
    formatPaymentMethod(method) {
      return method.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
    }
  }
}).mount('#app');
</script>
<?php renderFooter(); ?>