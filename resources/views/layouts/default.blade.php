<!doctype html>
<html>
<head>
  @include('includes.head', ['title' => $title])
  @stack('head')
</head>
<body>
<div class="p-4">
  <div id="main" class="max-w-2xl mx-auto md:mt-8">
    @yield('content')
  </div>
</div>

<a href="https://github.com/alancwoo/secret" class="fixed bottom-0 right-0 p-4" title="Secret on Github">π</a>

@yield('footer')

</body>
</html>
