<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($branding['favicon_url'] ?: '/assets/favicon.png') ?>">
    <link rel="stylesheet" href="/assets/style.css">
    <?php if (file_exists(__DIR__ . '/../public/assets/custom.css')): ?>
    <link rel="stylesheet" href="/assets/custom.css">
    <?php endif; ?>
    <?php if ($branding['color_primary'] !== '#228be6'): ?>
    <style>
        :root {
            --accent-color: <?= htmlspecialchars($branding['color_primary']) ?>;
            --accent-hover: <?= htmlspecialchars($branding['color_primary_hover']) ?>;
            --checkbox-checked: <?= htmlspecialchars($branding['color_primary']) ?>;
        }
    </style>
    <?php endif; ?>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
</head>
<body>
    <?= $content ?>
    <script src="/assets/app.js"></script>
    <?php if (file_exists(__DIR__ . '/../public/assets/custom.js')): ?>
    <script src="/assets/custom.js"></script>
    <?php endif; ?>
    <script>
    function setFaviconFromEmoji(emoji, letter, options) {
        options = options || {};
        var size = options.size || 32;
        var letterFont = options.letterFont || 'bold 14px sans-serif';
        var fillStyle = options.fillStyle || 'white';
        var strokeStyle = options.strokeStyle || 'black';
        var padding = options.padding || 2;

        var canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;
        var ctx = canvas.getContext('2d');

        // Draw emoji as base
        ctx.font = (size - 4) + 'px serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(emoji, size / 2, size / 2 + 2);

        // Draw letter overlay if provided
        if (letter) {
            ctx.font = letterFont;
            ctx.textAlign = 'right';
            ctx.textBaseline = 'bottom';
            ctx.lineWidth = 2;
            var x = size - padding;
            var y = size - padding;
            ctx.strokeStyle = strokeStyle;
            ctx.strokeText(letter, x, y);
            ctx.fillStyle = fillStyle;
            ctx.fillText(letter, x, y);
        }

        // Set favicon
        var link = document.querySelector('link[rel="icon"]');
        if (!link) {
            link = document.createElement('link');
            link.rel = 'icon';
            document.head.appendChild(link);
        }
        link.type = 'image/png';
        link.href = canvas.toDataURL('image/png');
    }

    function setFaviconFromImage(imageUrl, letter, options) {
        options = options || {};
        var size = options.size || 32;
        var font = options.letterFont || 'bold 14px sans-serif';
        var fillStyle = options.fillStyle || 'white';
        var strokeStyle = options.strokeStyle || 'black';
        var padding = options.padding || 2;

        var img = new Image();
        img.crossOrigin = 'anonymous';

        img.onload = function() {
            var canvas = document.createElement('canvas');
            canvas.width = size;
            canvas.height = size;
            var ctx = canvas.getContext('2d');

            // Draw the base favicon
            ctx.drawImage(img, 0, 0, size, size);

            // Draw letter overlay if provided
            if (letter) {
                ctx.font = font;
                ctx.textAlign = 'right';
                ctx.textBaseline = 'bottom';
                ctx.lineWidth = 2;
                var x = size - padding;
                var y = size - padding;
                ctx.strokeStyle = strokeStyle;
                ctx.strokeText(letter, x, y);
                ctx.fillStyle = fillStyle;
                ctx.fillText(letter, x, y);
            }

            // Replace the favicon
            var link = document.querySelector('link[rel="icon"]');
            if (!link) {
                link = document.createElement('link');
                link.rel = 'icon';
                document.head.appendChild(link);
            }
            link.type = 'image/png';
            link.href = canvas.toDataURL('image/png');
        };

        img.src = imageUrl;
    }

    document.addEventListener('DOMContentLoaded', function() {
        var faviconUrl = <?= json_encode($branding['favicon_url']) ?>;
        var faviconEmoji = <?= json_encode($branding['favicon_emoji']) ?>;
        var siteEmoji = <?= json_encode($branding['site_emoji']) ?>;
        var siteName = <?= json_encode($branding['site_name']) ?>;
        var customLetter = <?= json_encode($branding['favicon_letter']) ?>;
        var showLetter = <?= json_encode($branding['favicon_show_letter']) ?>;

        // Determine the letter to show (if any)
        var letter = null;
        if (showLetter) {
            letter = customLetter || siteName.charAt(0).toUpperCase();
        }

        var options = {
            letterFont: 'bold 16px sans-serif',
            padding: 1
        };

        // Determine favicon source: custom URL > custom emoji > site emoji
        if (faviconUrl) {
            setFaviconFromImage(faviconUrl, letter, options);
        } else {
            var emoji = faviconEmoji || siteEmoji;
            if (emoji) {
                setFaviconFromEmoji(emoji, letter, options);
            }
        }
    });
    </script>
</body>
</html>
