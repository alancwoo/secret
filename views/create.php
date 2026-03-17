<?php $layout = true; ?>

<img src="/images/header.png" alt="Secret" class="header-img">

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
          <div>Drop a file here or click to select</div>
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
          <option value="1D">1 Day</option>
          <option value="3D">3 Days</option>
          <option value="7D">7 Days</option>
          <option value="never">Unlimited</option>
        </select>
        <p class="form-hint">Secret expires after...</p>
      </div>

      <div>
        <label for="max-views">Max Views</label>
        <select id="max-views">
          <option value="1">1 view</option>
          <option value="3">3 views</option>
          <option value="5">5 views</option>
          <option value="10">10 views</option>
          <option value="0">Unlimited</option>
        </select>
        <p class="form-hint">Secret will be deleted after this many views</p>
      </div>

      <?php if ($requirePassword): ?>
      <div>
        <label for="password">Upload Password</label>
        <input id="password" type="password" placeholder="******************" required>
        <p class="form-hint">Password to create a new secret</p>
      </div>
      <?php endif; ?>
    </div>

    <button class="btn mt-lg" type="submit" id="submit-btn">OK</button>
  </form>

  <div id="progress" class="hidden mt-lg msg-center pulse">Encrypting...</div>
  <div id="error" class="hidden mt-lg msg-error"></div>
</div>

<!-- === Success result === -->
<div id="result" class="hidden">
  <p class="msg-success">Your secret has been encrypted and saved securely. Please share the link below.</p>

  <div id="result-full-url" class="result-box mt-md" onclick="copyText(this.textContent)"></div>
  <p class="copy-hint">Click to copy</p>

  <div class="mt-lg">
    <p class="msg-success">For increased security you may choose to send the URL and KEY separately (e.g. one by email and another by text)</p>

    <p class="section-label">URL</p>
    <div id="result-url" class="result-box" onclick="copyText(this.textContent)"></div>

    <p class="section-label">Key</p>
    <div id="result-key" class="result-box" onclick="copyText(this.textContent)"></div>
  </div>

  <div class="mt-lg">
    <p id="result-summary"></p>
    <p class="mt-sm"><button class="delete-link" onclick="deleteCreatedSecret()">Click here to delete it immediately</button></p>
  </div>
</div>

<script>
(function () {
  // State
  var contentType = 'text';
  var selectedFile = null;
  var fileData = null;
  var createdId = null;
  var createdKey = null;

  var expiryLabels = {
    'T5M': '5 Minutes', 'T1H': '1 Hour', 'T12H': '12 Hours',
    '1D': '1 Day', '3D': '3 Days', '7D': '7 Days', 'never': 'Never'
  };

  // Expose to inline handlers
  window.toggleType = function (type) {
    contentType = type;
    document.getElementById('text-input').classList.toggle('hidden', type !== 'text');
    document.getElementById('file-input').classList.toggle('hidden', type !== 'file');
  };

  window.handleFileSelect = function (input) {
    var file = input.files[0];
    if (!file) return;
    var MAX = 10 * 1024 * 1024;
    if (file.size > MAX) {
      showError('File size exceeds limit (max ' + Secret.formatFileSize(MAX) + ')');
      input.value = '';
      return;
    }
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
  };

  window.removeFile = function () {
    selectedFile = null;
    fileData = null;
    document.getElementById('file-picker').value = '';
    document.getElementById('dropzone-prompt').classList.remove('hidden');
    document.getElementById('dropzone-info').classList.add('hidden');
    document.getElementById('file-remove').classList.add('hidden');
  };

  // Drag and drop
  var dz = document.getElementById('dropzone');
  dz.addEventListener('dragover', function (e) { e.preventDefault(); dz.classList.add('dragover'); });
  dz.addEventListener('dragleave', function () { dz.classList.remove('dragover'); });
  dz.addEventListener('drop', function (e) {
    e.preventDefault();
    dz.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
      // Switch to file mode
      contentType = 'file';
      document.querySelector('input[name="content_type"][value="file"]').checked = true;
      window.toggleType('file');
      // Assign to picker and trigger handler
      document.getElementById('file-picker').files = e.dataTransfer.files;
      window.handleFileSelect(document.getElementById('file-picker'));
    }
  });

  window.handleSubmit = function (e) {
    e.preventDefault();
    createSecret();
    return false;
  };

  window.deleteCreatedSecret = function () {
    if (!createdId) return;
    fetch('/api/secret/' + createdId, { method: 'DELETE' }).then(function () {
      location.reload();
    });
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

    // Show progress
    document.getElementById('secret-form').classList.add('hidden');
    document.getElementById('progress').classList.remove('hidden');

    try {
      // Prepare data
      var dataToEncrypt;
      if (contentType === 'text') {
        dataToEncrypt = new TextEncoder().encode(document.getElementById('message').value);
      } else {
        dataToEncrypt = fileData;
      }

      // Generate key + IV, encrypt
      var iv = crypto.getRandomValues(new Uint8Array(12));
      var key = await Secret.generateKey();
      var encrypted = await Secret.encrypt(dataToEncrypt, key, iv);
      createdKey = await Secret.exportKey(key);

      // Build payload
      var body = {
        content: Secret.arrayBufferToBase64(encrypted),
        iv: Secret.arrayBufferToBase64(iv),
        expires: document.getElementById('expires').value,
        maxViews: parseInt(document.getElementById('max-views').value, 10),
        password: document.getElementById('password') ? document.getElementById('password').value : '',
        isFile: contentType === 'file'
      };
      if (contentType === 'file') {
        body.filename = selectedFile.name;
        body.mimetype = selectedFile.type || 'application/octet-stream';
      }

      document.getElementById('progress').textContent = 'Uploading...';

      var response = await fetch('/api/secret', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      var json = await response.json();

      if (response.status === 201) {
        createdId = json.id;
        showResult();
      } else {
        document.getElementById('progress').classList.add('hidden');
        document.getElementById('secret-form').classList.remove('hidden');
        showError(json.error || 'An error occurred');
        if (document.getElementById('password')) {
          document.getElementById('password').value = '';
          document.getElementById('password').focus();
        }
      }

    } catch (err) {
      console.error(err);
      document.getElementById('progress').classList.add('hidden');
      document.getElementById('secret-form').classList.remove('hidden');
      showError(err.message || 'An unexpected error occurred');
    }
  }

  function showResult() {
    var url = location.origin + '/s/' + createdId;
    var full = url + '#' + createdKey;
    var expiresVal = document.getElementById('expires').value;
    var maxViewsVal = parseInt(document.getElementById('max-views').value, 10);

    document.getElementById('result-full-url').textContent = full;
    document.getElementById('result-url').textContent = url;
    document.getElementById('result-key').textContent = '#' + createdKey;

    // Build summary
    var parts = [];
    if (expiresVal === 'never') {
      parts.push('This link has no time expiry');
    } else {
      parts.push('This link expires in ' + (expiryLabels[expiresVal] || expiresVal));
    }
    if (maxViewsVal === 0) {
      parts.push('can be viewed unlimited times');
    } else if (maxViewsVal === 1) {
      parts.push('can only be viewed once');
    } else {
      parts.push('can be viewed ' + maxViewsVal + ' times');
    }
    document.getElementById('result-summary').textContent = parts.join(', and ') + '.';

    document.getElementById('create-form').classList.add('hidden');
    document.getElementById('result').classList.remove('hidden');
  }
})();
</script>
