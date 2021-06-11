@extends('layouts.default', ['title' => 'Secret'])

@section('content')
<div x-data="secret()" x-init="mounted">
  <template x-if="message">
    <div>
      <div class="select-none text-red-500 text-sm italic leading-tight mb-6 text-center">This message has already been deleted from the server and cannot be retrieved again. Please save the contents securely before closing the browser.</div>
      <div class="select-color w-full bg-gray-200 rounded p-4" x-text="message"></div>
    </div>
  </template>
  <template x-if="error">
    <div>
      <div class="mt-3 text-red-400 text-center" x-text="error"></div>
      <img src="/images/cliparts/{{ rand(1,16) }}.jpg" class="max-w-md mx-auto mt-6" />
    </div>
  </template>
</div>
@stop

@section('footer')
<script>
  function secret() {
    return {
      key: window.location.hash.substring(1),
      id: @json($id),
      message: null,
      error: null,

      async mounted() {
        if (this.key) {
          this.decryptMessage()
        }
      },

      async decryptMessage() {
        try {
          const content = "{{ $content }}"
          const ivStr = "{{ $iv }}"

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
          )

          const decrypted = await window.crypto.subtle.decrypt(
            {
              name: "AES-GCM",
              iv: window.b64.decode(ivStr)
            },
            decryptionKey,
            window.b64.decode(content)
          )

          const decoded = new window.TextDecoder().decode(new Uint8Array(decrypted))

          fetch(window.location.origin + window.location.pathname, {
            method: 'DELETE'
          })

          this.message = decoded

          window.onbeforeunload = function() {
            return true
          }

        } catch(e) {
          this.error = "The message does not exist, expired, or the key is incorrect. Please try again." 
        }
      }
    }
  }  
</script>
