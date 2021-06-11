<?php

namespace App\Http\Controllers;

use App\Models\Secret;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use DateTime;
use DateInterval;

class SecretController extends Controller
{
  private function deleteSecret($id)
  {
    Secret::findOrFail($id)->delete();
  }

  public function show($id)
  {
    $secret = Secret::findOrFail($id);
    return view('secret-show', [
      'id' => $secret->id,
      'iv' => $secret->iv,
      'content' => $secret->content
    ]);
  }

  public function create(Request $request)
  {
    $data = $request->json()->all();

    // Check password
    if ($data['password'] == env('NEW_ITEM_PASSWORD')) {

      // Set expiry
      $now = new \DateTime();
      $expiry = $now->add(new \DateInterval("P{$data['expires']}"));

      // Create and store secret
      $secret = new Secret;
      $secret->content = $data['content'];
      $secret->expires = $expiry;
      $secret->iv = $data['iv'];
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
