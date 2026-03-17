<?php $layout = true; ?>

<div id="loading" class="msg-center">
  <div class="pulse">Decrypting...</div>
</div>

<div id="text-result" class="hidden">
  <p id="text-warning" class="msg-warning mb-lg"></p>
  <div id="secret-text" class="secret-content"></div>
</div>

<div id="file-result" class="hidden">
  <p id="file-warning" class="msg-warning mb-lg"></p>
  <div class="msg-center">
    <div id="dl-filename" class="bold mb-md"></div>
    <div id="dl-mimetype" class="file-meta mb-lg"></div>
    <a id="dl-link" class="btn btn-download" download>Download File</a>
  </div>
</div>

<div id="show-error" class="hidden">
  <p class="msg-error msg-center"></p>
  <img src="/images/cliparts/<?= rand(1, 16) ?>.jpg" class="error-image" alt="">
</div>

<script>
(function () {
  var id = <?= json_encode($id) ?>;
  var key = location.hash.substring(1);
  var allowedTags = <?= json_encode(explode(',', $allowedTags ?? 'br,a')) ?>;

  if (!Secret.isSecureContext()) {
    showError('Decryption requires HTTPS. Please access this site over HTTPS.');
    return;
  }

  if (!key) {
    showError('No decryption key provided in URL fragment.');
    return;
  }

  fetchAndDecrypt();

  async function fetchAndDecrypt() {
    try {
      var response = await fetch('/api/secret/' + id);
      if (!response.ok) throw new Error('Not found');
      var data = await response.json();

      var decryptionKey = await Secret.importKey(key);
      var encryptedData = Secret.base64ToArrayBuffer(data.content);
      var iv = Secret.base64ToArrayBuffer(data.iv);
      var decrypted = await Secret.decrypt(encryptedData, decryptionKey, iv);

      // Build warning message based on view limits
      var lastView = data.lastView;
      var warningMsg;
      if (lastView) {
        warningMsg = 'This has been deleted from the server and cannot be retrieved again. Please save the contents securely before closing the browser.';
      } else if (data.maxViews === 0) {
        warningMsg = 'View ' + data.views + ' — this secret has no view limit and will expire by time only.';
      } else {
        var remaining = data.maxViews - data.views;
        warningMsg = 'View ' + data.views + ' of ' + data.maxViews + '. ' + remaining + ' view' + (remaining === 1 ? '' : 's') + ' remaining before deletion.';
      }

      document.getElementById('loading').classList.add('hidden');

      if (data.isFile) {
        var filename = data.filename || 'download';
        var mimetype = data.mimetype || 'application/octet-stream';
        var blob = new Blob([decrypted], { type: mimetype });
        var url = URL.createObjectURL(blob);

        document.getElementById('file-warning').textContent = warningMsg;
        document.getElementById('dl-filename').textContent = filename;
        document.getElementById('dl-mimetype').textContent = mimetype;
        var link = document.getElementById('dl-link');
        link.href = url;
        link.download = filename;
        document.getElementById('file-result').classList.remove('hidden');
      } else {
        var decoded = new TextDecoder().decode(new Uint8Array(decrypted));
        var sanitized = Secret.sanitize(decoded.replace(/\n/g, '<br>'), allowedTags);
        document.getElementById('text-warning').textContent = warningMsg;
        document.getElementById('secret-text').innerHTML = sanitized;
        document.getElementById('text-result').classList.remove('hidden');
      }

      if (lastView) {
        window.onbeforeunload = function () { return true; };
      }

    } catch (e) {
      console.error(e);
      showError('The message does not exist, expired, or the key is incorrect. Please try again.');
    }
  }

  function showError(msg) {
    document.getElementById('loading').classList.add('hidden');
    var el = document.getElementById('show-error');
    el.querySelector('p').textContent = msg;
    el.classList.remove('hidden');
  }
})();
</script>
