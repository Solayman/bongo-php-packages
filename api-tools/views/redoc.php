<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Bongo API Documentation', ENT_QUOTES, 'UTF-8') ?> - Redoc</title>
    <style>body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }</style>
</head>
<body>
    <redoc spec-url="<?= htmlspecialchars($jsonRoute ?? '/api/openapi.json', ENT_QUOTES, 'UTF-8') ?>"></redoc>
    <script src="https://cdn.redoc.ly/redoc/latest/bundles/redoc.standalone.js"></script>
</body>
</html>
