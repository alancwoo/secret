@extends('layouts.default', ['title' => 'Secret'])

@section('content')
<div>
  <div class="select-color w-full bg-gray-200 rounded p-4">{{ $message }}</div>
  <div class="select-none text-red-500 text-sm italic leading-tight mt-3">This message has already been deleted from the server and cannot be viewed again. Please save securely.</div>
</div>
@stop

@section('footer')
<script>
window.onbeforeunload = function() {
  return true;
}
</script>
