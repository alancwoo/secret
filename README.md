# 🔒 Secret
A simple php ([lumen](https://lumen.laravel.com)) app for sharing sensitive text (basically like [onetimesecret](https://onetimesecret.com)), but with full end-to-end AES-256-GCM encryption so even the server has no access to the data, and developed with very simple deployment in mind.

## What is it for
I often need to send credentials or sensitive information to clients and colleagues and really prefer not to send these things over email/chat where they remain forever prone to breaches and also attached to a context in email threads (eg, it is clear such data is connected to a site/identity/account).

It is even better to send the URL and the KEY separately through different channels and instruct the user to recombine them in the address bar.

## Requirements
- Requires PHP7.x (Lumen does not seem to support PHP8 yet)
- [Must be hosted/served over https with a proper certificate](https://developer.mozilla.org/en-US/docs/Web/API/SubtleCrypto)

## Install
- Clone the repo
- Copy `.env.example` to `.env`
- Configure `APP_URL` with the url, `APP_KEY` with a random string, `NEW_ITEM_PASSWORD` with a password for the creation of new items. (A password is highly recommended. If no password is set, anyone can create secrets)
- `touch database/database.sqlite`
- `composer install`
- `php artisan migrate`

## Dev
- `composer install`
- `npm i`
- Set URL in `webpack.mix.js`
- `npx mix watch`

## Notes
- There's no rate limiter, so I definitely suggest setting a password.
- Not tested on IE/Edge, but from a look at the [Compatibility table](https://developer.mozilla.org/en-US/docs/Web/API/SubtleCrypto#browser_compatibility) the requirements should be supported
- Thank you [Pichiste](https://github.com/pichiste) for helping debug the nightmare of SubtleCrypto ArrayBuffer <> String conversions.
