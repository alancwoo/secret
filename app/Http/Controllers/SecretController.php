<?php

namespace App\Http\Controllers;

use App\Models\Secret;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use DateTime;
use DateInterval;

class SecretController extends Controller
{
  public function show($id)
  {
    $secret = Secret::findOrFail($id);

    $expires = strtotime($secret->expires);

    if (time() > $expires) {
      // Delete expired message
      $secret->delete();
      return response(view("errors.404"), 404);
    } else {
      // Return message contents
      return view('secret-show', [
        'id' => $secret->id,
        'content' => $secret->content,
        'iv' => $secret->iv
      ]);
    }

  }

  public function create(Request $request)
  {
    $data = $request->json()->all();
    $pass = $data['password'];

    // Check password
    if (env('NEW_ITEM_PASSWORD') === '' || $pass === env('NEW_ITEM_PASSWORD')) {

      // Set expiry
      $now = new \DateTime();
      $expiry = $now->add(new \DateInterval("P{$data['expires']}"));

      // Create and store secret
      $secret = new Secret;
      $secret->content = $data['content'];
      $secret->iv = $data['iv'];
      $secret->expires = $expiry;
      $secret->save();

      return response()->json([
        'id' => $secret->id
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
