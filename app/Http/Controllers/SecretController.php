<?php

namespace App\Http\Controllers;

use App\Models\Secret;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use DateTime;
use DateInterval;

class SecretController extends Controller
{
  private function decryptSecret($id, $key) {
    $secret = Secret::findOrFail($id);

    $key = hex2bin($key);
    $encrypted = base64_decode($secret->content);
    $cipher = $secret->cipher;
    $tag_length = env('TAG_LENGTH', 16);
    $iv_len = openssl_cipher_iv_length($cipher);
    $iv = substr($encrypted, 0, $iv_len);
    $ciphertext = substr($encrypted, $iv_len, -$tag_length);
    $tag = substr($encrypted, -$tag_length);

    return openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
  }

  private function deleteSecret($id) {
    Secret::findOrFail($id)->delete();
  }

  public function showSecret($id, $key)
  {
    $message = $this->decryptSecret($id, $key);

    if ($message) {
      $this->deleteSecret($id);
      return view('secret-view', ['message' => $message]);
    } else {
      return response(view("errors.404"), 404);
    }
  }

  public function create(Request $request)
  {
    $data = $request->json()->all();

    // Check if password for creation matches
    if ($data['password'] == env('NEW_ITEM_PASSWORD')) {

      // Sanitize content
      $plaintext = strip_tags($data['content']);

      // Set expiry
      $now = new \DateTime();
      $expiry = $now->add(new \DateInterval("P{$data['expires']}"));

      // Encrypt data
      $cipher = env('CIPHER', 'aes-256-gcm');

      // Generate random key
      $key = openssl_random_pseudo_bytes(env('KEY_LENGTH', 32));
      $key_string = bin2hex($key);
      $tag_length = env('TAG_LENGTH', 16);


      if (in_array($cipher, openssl_get_cipher_methods())) {
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag, "", $tag_length);
      } else {
        abort(500);
      }

      // Create and store secret
      $secret = new Secret;
      $secret->content = base64_encode($iv.$ciphertext.$tag);
      $secret->expires = $expiry;
      $secret->cipher = $cipher;
      $secret->save();

      return response()->json([
        'id' => $secret->id,
        'key' => $key_string,
        'url' => env('APP_URL') . "/{$secret->id}",
        'url_full' => env('APP_URL') . "/{$secret->id}/{$key_string}",
      ], 201);


    } else {
      return response()->json([
        'error' => "Password Incorrect"
      ], 401);
    }
  }

  public function delete($id)
  {
    Secret::findOrFail($id)->delete();
    return response('Deleted Successfully', 200);
  }
}
