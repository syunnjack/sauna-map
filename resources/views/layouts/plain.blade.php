<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#1a6b7a">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>@yield('title', config('app.name') . ' | 現在地から探す・今の混雑状況がわかるサウナ・銭湯マップ')</title>
  <meta name="description" content="@yield('description', '全国のサウナ・銭湯・スーパー銭湯を地図から探せる投稿型マップです。現在地から近い施設をすぐ見つけられ、今の混雑状況やサ活（写真付き口コミ）をリアルタイムで確認できます。')">
  <link rel="canonical" href="{{ url()->current() }}">

  <meta property="og:site_name" content="{{ config('app.name') }}">
  <meta property="og:type" content="website">
  <meta property="og:title" content="@yield('title', config('app.name') . ' | 現在地から探す・今の混雑状況がわかるサウナ・銭湯マップ')">
  <meta property="og:description" content="@yield('description', '全国のサウナ・銭湯・スーパー銭湯を地図から探せる投稿型マップです。現在地から近い施設をすぐ見つけられ、今の混雑状況やサ活（写真付き口コミ）をリアルタイムで確認できます。')">
  <meta property="og:url" content="{{ url()->current() }}">
  <meta property="og:locale" content="ja_JP">

  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="@yield('title', config('app.name') . ' | 現在地から探す・今の混雑状況がわかるサウナ・銭湯マップ')">
  <meta name="twitter:description" content="@yield('description', '全国のサウナ・銭湯・スーパー銭湯を地図から探せる投稿型マップです。現在地から近い施設をすぐ見つけられ、今の混雑状況やサ活（写真付き口コミ）をリアルタイムで確認できます。')">

  <link rel="icon" href="/favicon.ico" sizes="any">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    body { background-color: #f2f9fa; font-family: system-ui, -apple-system, sans-serif; }
    .btn { min-height: 44px; }
    .btn-line { background: #06c755; color: #fff; border: none; }
    .btn-line:hover { background: #05a848; color: #fff; }
    .btn-sauna { background: #1a6b7a; color: #fff; border: none; }
    .btn-sauna:hover { background: #14525d; color: #fff; }
  </style>
  @yield('styles')

  @stack('structured-data')
</head>
<body>
  <nav class="navbar navbar-dark p-2" style="background-color:#0f4b57;">
    <div class="container-fluid">
      <a href="{{ route('venues.index') }}" class="navbar-brand text-white text-decoration-none">♨️ {{ config('app.name') }}</a>
      <a href="{{ route('about') }}" class="text-white small text-decoration-none">サイトについて</a>
    </div>
  </nav>

  @yield('content')

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  @yield('scripts')
</body>
</html>
