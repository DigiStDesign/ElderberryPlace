<?php
require_once __DIR__ . '/includes/layout.php';
renderHeader('Logging out…');
?>
<main style="max-width:640px;margin:40px auto;padding:16px;">
  <p>Logging you out…</p>
  <noscript><p><a href="index.php">Back to home</a></p></noscript>
</main>

<script src="https://unpkg.com/vue@3.4.27/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.7/dist/axios.min.js"></script>
<script src="/assets/app.js"></script>
<script>
(async function(){
  try {
    await window.apiGetCsrf();               // set CSRF header
    await window.apiPost('/auth/logout',{}); // call API logout
  } catch(e) { /* ignore */ }
  window.location = (window.APP_BASE || '') + '/index.php';
})();
</script>
<?php renderFooter(); ?>
