<!doctype html>
<html>
<head>
  @include('includes.head', ['title' => $title])
</head>
<body>
<div class="p-4">
  <div id="main" class="max-w-2xl mx-auto mt-12">
    @yield('content')
  </div>
</div>

@yield('footer')

</body>
</html>
