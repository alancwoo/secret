@extends('layouts.default', ['title' => 'New Secret'])

@section('content')
<div x-data="secret()">
  <h1 class="font-bold mb-3">New Secret</h1>
  <form @submit.prevent="newSecret()" action="/secret" class="flex flex-col space-y-6">
    <textarea x-model="secret" x-bind:disabled="submitting" class="appearance-none block w-full bg-gray-200 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500 h-48" x-bind:class="{ 'text-gray-300 bg-gray-500': submitting }" placeholder="Enter your secret..." autofocus required></textarea>
    <div x-show="!submitting">
      <div class="flex space-x-6">
        <div class="w-full">
          <label class="block uppercase tracking-wide text-xs font-bold mb-2" for="expires">Expires</label>
          <div class="relative mb-2">
            <select x-model="expires" id="expires" class="block appearance-none w-full bg-gray-200 border border-gray-200 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500">
              <template x-for="option in periods">
                <option :value="option.value" :key="option.value" x-text="`Expires in ${option.text}`"></option>
              </template>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-700">
              <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
            </div>
          </div>
          <p class="text-gray-400 text-xs italic">Secret will always be deleted after viewing</p>
        </div>
        <div class="w-full">
          <label class="block uppercase tracking-wide text-xs font-bold mb-2" for="password">Password</label>
          <input id="password" type="password" x-model="password" id="password" class="appearance-none block w-full bg-gray-200 border border-gray-200 rounded py-3 px-4 mb-2 leading-tight focus:outline-none focus:bg-white focus:border-gray-500" placeholder="******************" required />
          <p class="text-gray-400 text-xs italic">Your password is set in the app config</p>
        </div>
      </div>
      <button class="block w-full hover:bg-gray-200 border-4 mt-6 p-4 rounded font-bold" type="submit">
        OK
      </button>
    </div>
  </form>

  <div class="mt-3" x-show="submitting && !data">
    <div class="w-full text-center pulse">Submitting...</div>
  </div>

  <div class="mt-3 text-red-400" x-show="error && !submitting" x-text="error"></div>

  <template x-if="data">
    <div class="mt-6">
      <h1 class="text-green-400">Your secret has been encrypted and saved securely. Please share the link below.</h1>
      <div class="border rounded border-gray-400 p-4 mt-3 bg-gray-200">
        <span class="break-all select-color" x-text="data.url_full" />
      </div>

      <div class="mt-6">
        <p class="text-green-400">For increased security you may choose to send the URL and KEY separately:</p>

        <h3 class="mt-3 block uppercase tracking-wide text-xs font-bold mb-2">URL</h3>
        <div class="border rounded border-gray-400 p-4 bg-gray-200">
          <span class="break-all select-color" x-text="data.url" />
        </div>

        <h3 class="mt-3 block uppercase tracking-wide text-xs font-bold mb-2">Key</h3>
        <div class="border rounded border-gray-400 p-4 bg-gray-200">
          <span class="break-all select-color" x-text="data.key" />
        </div>
      </div>

      <div class="mt-6 space-y-2">
        <p>This link expires in <span class="font-bold" x-text="expiryText"></span>, and can only be viewed once.</p>
        <p class="text-red-400"><a href="#" class="underline">Click here to delete it immediately</a></p>
      </div>
    </div>
  </template>
</div>

@stop


@section('footer')
<script>
  function secret() {
    return {
      submitting: false,
      secret: null,
      expires: '5M',
      password: null,
      error: null,
      data: null,
      key: null,
      periods: [
        {
          value: '5M',
          text: '5 Minutes',
        },
        {
          value: '1H',
          text: '1 Hour',
        },
        {
          value: '12H',
          text: '12 Hours',
        },
        {
          value: '1D',
          text: '1 Day',
        },
        {
          value: '3D',
          text: '3 Days',
        },
        {
          value: '7D',
          text: '7 Days',
        }
      ],

      get expiryText() {
        return this.periods.find(x => x.value === this.expires).text
      },

      encryptSecret() {
        let enc = new TextEncoder()
        let encoded = this.
      },
      
      async newSecret() {
        this.submitting = true

        try {
          const response = await fetch('/secret', {
            method: 'POST',
            mode: 'cors',
            cache: 'no-cache',
            body: JSON.stringify({
              content: this.content,
              expires: this.expires,
              password: this.password
            })
          })

          if (response.status == 201) {
            const json = await response.json()
            this.data = json
          } else {
            const json = await response.json()
            this.error = json.error
            window.setTimeout(() => {
              this.password = ''
              this.submitting = false

              this.$nextTick(() => {
                document.getElementById('password').focus()
              })
            }, 2500)
          }

        } catch(e) {
          this.error = e
        }
      }
    }
  }
</script>

@stop
