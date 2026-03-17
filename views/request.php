<?php $layout = true; ?>

<h1 class="bold mb-lg">Send a Secret</h1>

<?php if ($label): ?>
<p class="mb-lg">You've been asked to securely share: <strong><?= htmlspecialchars($label) ?></strong></p>
<?php else: ?>
<p class="mb-lg">Someone has requested that you securely share a secret with them.</p>
<?php endif; ?>

<!-- === Create form === -->
<div id="create-form">
  <form id="secret-form" onsubmit="return handleSubmit(event)">

    <div class="toggle-group">
      <label><input type="radio" name="content_type" value="text" checked onchange="toggleType(this.value)"> Text</label>
      <label><input type="radio" name="content_type" value="file" onchange="toggleType(this.value)"> File</label>
    </div>

    <!-- Text input -->
    <div id="text-input" class="form-group">
      <textarea id="message" placeholder="Enter your secret..." autofocus></textarea>
    </div>

    <!-- File input -->
    <div id="file-input" class="form-group hidden">
      <div id="dropzone" class="dropzone" onclick="document.getElementById('file-picker').click()">
        <input type="file" id="file-picker" onchange="handleFileSelect(this)">
        <div id="dropzone-prompt">
          <div>Drop, paste, or click to select a file</div>
          <div class="form-hint">Max 10MB</div>
        </div>
        <div id="dropzone-info" class="hidden">
          <div id="file-name" class="file-info bold"></div>
          <div id="file-size" class="file-meta"></div>
          <div id="file-type" class="file-meta"></div>
        </div>
      </div>
      <div id="file-remove" class="hidden mt-sm">
        <span class="file-remove" onclick="removeFile()">Remove</span>
      </div>
    </div>

    <div class="form-row">
      <div>
        <label for="expires">Time Expiry</label>
        <select id="expires">
          <option value="T5M">5 Minutes</option>
          <option value="T1H">1 Hour</option>
          <option value="T12H">12 Hours</option>
          <option value="1D" selected>1 Day</option>
          <option value="3D">3 Days</option>
          <option value="7D">7 Days</option>
        </select>
        <p class="form-hint">When the secret expires by time</p>
      </div>

      <div>
        <label for="max-views">Max Views</label>
        <select id="max-views">
          <option value="1" selected>1 view</option>
          <option value="3">3 views</option>
          <option value="5">5 views</option>
          <option value="10">10 views</option>
        </select>
        <p class="form-hint">Deleted after this many views</p>
      </div>
    </div>

    <button class="btn mt-lg" type="submit" id="submit-btn">Encrypt &amp; Send</button>
  </form>

  <div id="progress" class="hidden mt-lg">
    <div class="msg-center" id="progress-text">Encrypting...</div>
    <div class="progress-bar mt-sm"><div class="progress-bar-fill" id="progress-fill"></div></div>
  </div>
  <div id="error" class="hidden mt-lg msg-error"></div>
</div>

<!-- === Success result === -->
<div id="result" class="hidden">
  <p class="msg-success mb-lg">Your secret has been encrypted and saved. Please send the following link and key back to the person who requested it, using your preferred channel.</p>

  <p class="section-label">Full Link (URL + Key)</p>
  <div id="result-full-url" class="result-box" onclick="copyText(this.textContent)"></div>
  <p class="copy-hint">Click to copy</p>

  <div class="mt-lg">
    <p class="msg-success">For increased security, send the URL and KEY separately through different channels.</p>

    <p class="section-label">URL</p>
    <div id="result-url" class="result-box" onclick="copyText(this.textContent)"></div>

    <p class="section-label">Key</p>
    <div id="result-key" class="result-box" onclick="copyText(this.textContent)"></div>
  </div>

  <p class="form-hint mt-lg">This request link is now used and cannot be accessed again.</p>
</div>

<script>
(function () {
  var contentType = 'text';
  var selectedFile = null;
  var fileData = null;
  var token = <?= json_encode($token) ?>;

  window.toggleType = function (type) {
    contentType = type;
    document.getElementById('text-input').classList.toggle('hidden', type !== 'text');
    document.getElementById('file-input').classList.toggle('hidden', type !== 'file');
  };

  var MAX_FILE = 10 * 1024 * 1024;

  function acceptFile(file) {
    if (!file) return;
    if (file.size > MAX_FILE) {
      showError('File size exceeds limit (max ' + Secret.formatFileSize(MAX_FILE) + ')');
      return;
    }
    hideError();
    selectedFile = file;
    document.getElementById('file-name').textContent = file.name;
    document.getElementById('file-size').textContent = Secret.formatFileSize(file.size);
    document.getElementById('file-type').textContent = file.type || 'Unknown type';
    document.getElementById('dropzone-prompt').classList.add('hidden');
    document.getElementById('dropzone-info').classList.remove('hidden');
    document.getElementById('file-remove').classList.remove('hidden');

    var reader = new FileReader();
    reader.onload = function (e) { fileData = e.target.result; };
    reader.readAsArrayBuffer(file);
  }

  function switchToFile() {
    contentType = 'file';
    document.querySelector('input[name="content_type"][value="file"]').checked = true;
    window.toggleType('file');
  }

  window.handleFileSelect = function (input) {
    acceptFile(input.files[0]);
  };

  window.removeFile = function () {
    selectedFile = null;
    fileData = null;
    document.getElementById('file-picker').value = '';
    document.getElementById('dropzone-prompt').classList.remove('hidden');
    document.getElementById('dropzone-info').classList.add('hidden');
    document.getElementById('file-remove').classList.add('hidden');
  };

  var dz = document.getElementById('dropzone');
  dz.addEventListener('dragover', function (e) { e.preventDefault(); dz.classList.add('dragover'); });
  dz.addEventListener('dragleave', function () { dz.classList.remove('dragover'); });
  dz.addEventListener('drop', function (e) {
    e.preventDefault();
    dz.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
      switchToFile();
      acceptFile(e.dataTransfer.files[0]);
    }
  });

  document.addEventListener('paste', function (e) {
    var items = e.clipboardData && e.clipboardData.items;
    if (!items) return;
    for (var i = 0; i < items.length; i++) {
      if (items[i].kind === 'file') {
        e.preventDefault();
        var file = items[i].getAsFile();
        if (file) {
          switchToFile();
          acceptFile(file);
        }
        return;
      }
    }
  });

  window.handleSubmit = function (e) {
    e.preventDefault();
    createSecret();
    return false;
  };

  window.copyText = function (text) {
    if (navigator.clipboard) navigator.clipboard.writeText(text.trim());
  };

  function showError(msg) {
    var el = document.getElementById('error');
    el.textContent = msg;
    el.classList.remove('hidden');
  }

  function hideError() {
    document.getElementById('error').classList.add('hidden');
  }

  function setProgress(percent, text) {
    if (text) document.getElementById('progress-text').textContent = text;
    document.getElementById('progress-fill').style.width = percent + '%';
  }

  function uploadWithProgress(url, jsonBody) {
    return new Promise(function (resolve, reject) {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', url);
      xhr.setRequestHeader('Content-Type', 'application/json');

      xhr.upload.addEventListener('progress', function (e) {
        if (e.lengthComputable) {
          var pct = Math.round((e.loaded / e.total) * 100);
          setProgress(pct, 'Uploading... ' + pct + '%');
        }
      });

      xhr.addEventListener('load', function () {
        var body;
        try { body = JSON.parse(xhr.responseText); } catch (e) { body = xhr.responseText; }
        resolve({ status: xhr.status, body: body });
      });

      xhr.addEventListener('error', function () { reject(new Error('Network error')); });
      xhr.addEventListener('abort', function () { reject(new Error('Upload aborted')); });

      xhr.send(JSON.stringify(jsonBody));
    });
  }

  async function createSecret() {
    hideError();

    if (!Secret.isSecureContext()) {
      showError('Encryption requires HTTPS. Please access this site over HTTPS.');
      return;
    }

    if (contentType === 'text') {
      var msg = document.getElementById('message').value;
      if (!msg.trim()) { showError('Please enter a message'); return; }
    }
    if (contentType === 'file' && !selectedFile) {
      showError('Please select a file'); return;
    }

    document.getElementById('secret-form').classList.add('hidden');
    document.getElementById('progress').classList.remove('hidden');
    setProgress(0, 'Encrypting...');

    try {
      var dataToEncrypt;
      if (contentType === 'text') {
        dataToEncrypt = new TextEncoder().encode(document.getElementById('message').value);
      } else {
        dataToEncrypt = fileData;
      }

      var iv = crypto.getRandomValues(new Uint8Array(12));
      var key = await Secret.generateKey();
      var encrypted = await Secret.encrypt(dataToEncrypt, key, iv);
      var exportedKey = await Secret.exportKey(key);

      setProgress(0, 'Uploading...');

      var body = {
        content: Secret.arrayBufferToBase64(encrypted),
        iv: Secret.arrayBufferToBase64(iv),
        expires: document.getElementById('expires').value,
        maxViews: parseInt(document.getElementById('max-views').value, 10),
        isFile: contentType === 'file'
      };
      if (contentType === 'file') {
        body.filename = selectedFile.name;
        body.mimetype = selectedFile.type || 'application/octet-stream';
      }

      var result = await uploadWithProgress('/api/request/' + token + '/submit', body);

      if (result.status === 201) {
        setProgress(100, 'Done');
        var url = location.origin + '/s/' + result.body.id;
        var full = url + '#' + exportedKey;
        document.getElementById('result-full-url').textContent = full;
        document.getElementById('result-url').textContent = url;
        document.getElementById('result-key').textContent = '#' + exportedKey;
        document.getElementById('create-form').classList.add('hidden');
        document.getElementById('result').classList.remove('hidden');
      } else {
        document.getElementById('progress').classList.add('hidden');
        document.getElementById('secret-form').classList.remove('hidden');
        showError(result.body.error || 'An error occurred');
      }

    } catch (err) {
      console.error(err);
      document.getElementById('progress').classList.add('hidden');
      document.getElementById('secret-form').classList.remove('hidden');
      showError(err.message || 'An unexpected error occurred');
    }
  }
})();
</script>
