<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
    <title>QR Dasturxon — Kassa</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    @include('miniapp._styles')
</head>
<body>
    @include('miniapp._cashier')

    <script src="/js/miniapp/i18n.js"></script>
    <script src="/js/miniapp/api.js"></script>
    <script src="/js/miniapp/cashier.js"></script>
    <script>window.MiniAppCashier.init();</script>
</body>
</html>
