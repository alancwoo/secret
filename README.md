# Secret
This is a tool for sharing sensitive text (basically like [onetimesecret](https://onetimesecret.com/)), but with end-to-end encryption with AES-256-GCM so even the server has no access to the data and made to be very easily self hosted,

## Requirements
- Requires PHP7.x (Lumen does not seem to support PHP8 yet)

## Install
- Clone the repo
- Copy `.env.example` to `.env`
- Configure `APP_URL` with the url, `APP_KEY` with a random string, `NEW_ITEM_PASSWORD` with a password for the creation of new items. (If no password is set, anyone can create secrets)
- `touch database/database.sqlite`
- `php artisan migrate`

## Dev
- `composer install`
- `npm i`
- Set URL in `webpack.mix.js` and run `npx mix watch`

## Notes
- There's no rate limiter, so I definitely suggest setting a password.
- Thank you [Pichiste](https://github.com/pichiste) for helping debug the nightmare of SubtleCrypto ArrayBuffer <> String conversions.
