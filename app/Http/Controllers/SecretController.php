<?php

namespace App\Http\Controllers;

use App\Models\Secret;
use Illuminate\Http\Request;

class SecretController extends Controller
{
  public function showSecret($id, $key)
  {
    return Secret::findOrFail($id);
    // return response()->json(Secret::findOrFail($id));
    // try {
    //   $secret = Secret::findOrFail($id);
    //   return "Bob";
    // } catch {
    //   return "BOOB";
    // }
  }

  public function create(Request $request)
  {
    $secret = Secret::create($request->all());
    return response()->json($secret, 201);
  }

  // public function update($id, Request $request)
  // {
  //   $author = Author::findOrFail($id);
  //   $author->update($request->all());

  //   return response()->json($author, 200);
  // }

  // public function delete($id)
  // {
  //   Author::findOrFail($id)->delete();
  //   return response('Deleted Successfully', 200);
  // }
}
