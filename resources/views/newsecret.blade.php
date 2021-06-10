@extends('layouts.default')

@section('content')
<div>
  <form method="POST" action="/secret">
    @csrf

    <textarea></textarea>

  </form>
</div>

@stop
