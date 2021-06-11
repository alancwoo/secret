@extends('layouts.default', ['title' => 'Not Found'])

@section('content')
<div>
  <div class="mt-3 text-red-400 text-center">The message does not exist, expired, or the key is incorrect. Please try again.</div>
  <img src="/images/cliparts/{{ rand(1,16) }}.jpg" class="max-w-md mx-auto mt-6" />
</div>
@stop
