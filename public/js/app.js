/* ==========================================================================
   Secret — Client-side encryption utilities
   No dependencies. Uses the Web Crypto API (requires HTTPS).
   ========================================================================== */

var Secret = {

  // --- Base64 helpers ---

  arrayBufferToBase64: function (buffer) {
    var bytes = new Uint8Array(buffer);
    var binary = '';
    for (var i = 0; i < bytes.byteLength; i++) {
      binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
  },

  base64ToArrayBuffer: function (base64) {
    var binary = atob(base64);
    var bytes = new Uint8Array(binary.length);
    for (var i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
  },

  // --- Crypto wrappers ---

  generateKey: function () {
    return crypto.subtle.generateKey(
      { name: 'AES-GCM', length: 256 },
      true,
      ['encrypt', 'decrypt']
    );
  },

  exportKey: function (key) {
    return crypto.subtle.exportKey('jwk', key).then(function (jwk) {
      return jwk.k;
    });
  },

  importKey: function (base64Key) {
    return crypto.subtle.importKey(
      'jwk',
      {
        k: base64Key,
        alg: 'A256GCM',
        ext: true,
        key_ops: ['encrypt', 'decrypt'],
        kty: 'oct'
      },
      { name: 'AES-GCM', length: 256 },
      false,
      ['decrypt']
    );
  },

  encrypt: function (data, key, iv) {
    return crypto.subtle.encrypt({ name: 'AES-GCM', iv: iv }, key, data);
  },

  decrypt: function (data, key, iv) {
    return crypto.subtle.decrypt({ name: 'AES-GCM', iv: new Uint8Array(iv) }, key, data);
  },

  // --- Secure context check ---

  isSecureContext: function () {
    return window.isSecureContext || location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
  },

  // --- Simple HTML sanitiser (allowlist-based) ---

  sanitize: function (html, allowedTags) {
    var div = document.createElement('div');
    div.innerHTML = html;

    function clean(node) {
      var children = Array.prototype.slice.call(node.childNodes);
      children.forEach(function (child) {
        if (child.nodeType === 3) return; // text node — keep
        if (child.nodeType === 1) {       // element node
          var tag = child.tagName.toLowerCase();
          if (allowedTags.indexOf(tag) === -1) {
            // Replace disallowed element with its text content
            var text = document.createTextNode(child.textContent);
            node.replaceChild(text, child);
          } else {
            // Strip all attributes from allowed elements
            while (child.attributes.length > 0) {
              child.removeAttribute(child.attributes[0].name);
            }
            clean(child);
          }
        } else {
          node.removeChild(child);
        }
      });
    }

    clean(div);
    return div.innerHTML;
  },

  // --- File size formatting ---

  formatFileSize: function (bytes) {
    if (bytes === 0) return '0 Bytes';
    var k = 1024;
    var sizes = ['Bytes', 'KB', 'MB', 'GB'];
    var i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }
};
