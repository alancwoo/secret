@extends('layouts.default', ['title' => 'Secret'])

@section('content')
<div x-data="secret()" x-init="mounted">
  <template x-if="message">
    <div>
      <div class="select-color w-full bg-gray-200 rounded p-4" x-text="iv"></div>
      <div class="select-none text-red-500 text-sm italic leading-tight mt-3">This message has already been deleted from the server and cannot be viewed again. Please save securely.</div>
    </div>
  </template>
  <template x-if="error">
    <div class="mt-3 text-red-400" x-text="error"></div>
  </template>
</div>
@stop

@section('footer')
<script>
  function str2ab(str) {
    var buf = new ArrayBuffer(str.length*2)
    var bufView = new Uint16Array(buf)
    for (var i=0, strLen=str.length; i < strLen; i++) {
      bufView[i] = str.charCodeAt(i)
    }
    return buf
  }

  function secret() {
    return {
      key: window.location.hash.substring(1),
      id: @json($id),
      message: null,
      error: null,
      iv: "{{ $iv }}",
      content: "{{ $content }}",

      async mounted() {
        if (this.key) {
          this.decryptMessage()
        }
      },

      async decryptMessage() {
        try {
          const decryptionKey = await window.crypto.subtle.importKey(
            "jwk",
            {
              k: this.key,
              alg: "A256GCM",
              ext: true,
              key_ops: ["encrypt", "decrypt"],
              kty: "oct",
            },
            { name: "AES-GCM", length: 256 },
            false, // extractable
            ["decrypt"],
          );

          const decrypted = await window.crypto.subtle.decrypt(
            { name: "AES-GCM", iv: str2ab(this.iv) },
            decryptionKey,
            str2ab(this.content)
          )

          const decoded = new window.TextDecoder().decode(new Uint8Array(decrypted));

        } catch(e) {
          this.error = e
        }
      }
    }
  }

  window.onbeforeunload = function() {
    return true
  }
</script>
