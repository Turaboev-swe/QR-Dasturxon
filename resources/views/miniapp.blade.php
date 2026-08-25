<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
    <title>QR Dasturxon</title>
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    @include('miniapp._styles')
</head>
<body>
    @include('miniapp._customer')

    <script src="/js/miniapp/i18n.js"></script>
    <script src="/js/miniapp/api.js"></script>
    <script src="/js/miniapp/customer.js"></script>
    <script>
        window.MiniAppCustomer.wireEvents();
        window.MiniAppCustomer.init();
    </script>
</body>
</html>
