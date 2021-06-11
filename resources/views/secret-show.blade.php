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
  function secret() {
    return {
      key: window.location.hash.substring(1),
      id: @json($id),
      message: null,
      error: null,
      iv: null,
      content: null,

      async mounted() {
        if (this.key) {
          fetch(window.location.origin + window.location.pathname + '/blob').then(async (data) => {
            this.content = await data.arrayBuffer()

            fetch(window.location.origin + window.location.pathname + '/iv').then(async (data) => {
              this.iv = await data.arrayBuffer()
              this.decryptMessage()
            })
          })
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
          console.log(this.content)
          console.log(this.iv)

          const decrypted = await window.crypto.subtle.decrypt(
            { name: "AES-GCM", iv: this.iv },
            decryptionKey,
            this.content
          )

          const decoded = new window.TextDecoder().decode(new Uint8Array(decrypted));

        } catch(e) {
          this.error = e
        }
      }
    }
  }

  window.onbeforeunload = function() {
    return true;
  }
</script>
