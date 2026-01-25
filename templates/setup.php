<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - <?= htmlspecialchars($branding['site_name']) ?></title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($branding['favicon_url'] ?: '/assets/favicon.png') ?>">
    <link rel="stylesheet" href="/assets/style.css">
    <?php if (file_exists(__DIR__ . '/../public/assets/custom.css')): ?>
    <link rel="stylesheet" href="/assets/custom.css">
    <?php endif; ?>
    <?php if ($branding['color_primary'] !== '#3b82f6'): ?>
    <style>
        :root {
            --accent-color: <?= htmlspecialchars($branding['color_primary']) ?>;
            --accent-hover: <?= htmlspecialchars($branding['color_primary_hover']) ?>;
        }
    </style>
    <?php endif; ?>
</head>
<body>
    <header class="site-header">
        <div>
            <div class="header-left">
                <span class="site-logo">
                    <?php if ($branding['logo_url']): ?>
                    <img src="<?= htmlspecialchars($branding['logo_url']) ?>" alt="<?= htmlspecialchars($branding['site_name']) ?>" style="max-width: <?= htmlspecialchars($branding['logo_width']) ?>px;">
                    <?php else: ?>
                    <?php if ($branding['site_emoji']): ?>
                    <span class="site-logo-emoji"><?= htmlspecialchars($branding['site_emoji']) ?></span>
                    <?php endif; ?>
                    <?= htmlspecialchars($branding['site_name']) ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="header-right">
                <?php if ($branding['external_link_url']): ?>
                <a href="<?= htmlspecialchars($branding['external_link_url']) ?>" class="header-external-link" target="_blank" rel="noopener noreferrer">
                    <?php if ($branding['external_link_logo']): ?>
                    <img src="<?= htmlspecialchars($branding['external_link_logo']) ?>" alt="<?= htmlspecialchars($branding['external_link_name']) ?>" width="16" height="16">
                    <?php endif; ?>
                    <?= htmlspecialchars($branding['external_link_name']) ?> &rarr;
                </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="password-page">
        <div class="password-container">
            <div class="password-icon">&#128075;</div>
            <h1>Welcome to <?= htmlspecialchars($branding['site_name']) ?></h1>
            <p class="password-section-name">Create your administrator account to get started.</p>

            <?php if (!empty($setupError)): ?>
            <div class="password-error"><?= htmlspecialchars($setupError) ?></div>
            <?php endif; ?>

            <form method="POST" action="/setup" class="password-form" id="setup-form">
                <?= \App\Auth::csrfField() ?>
                <input
                    type="text"
                    name="name"
                    placeholder="Your name"
                    class="password-input"
                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                    autofocus
                    required
                >
                <input
                    type="email"
                    name="email"
                    placeholder="Email address"
                    class="password-input"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                >
                <input
                    type="password"
                    name="password"
                    id="setup-password"
                    placeholder="Password (min 8 characters)"
                    class="password-input"
                    minlength="8"
                    required
                >
                <input
                    type="password"
                    name="password_confirm"
                    id="setup-password-confirm"
                    placeholder="Confirm password"
                    class="password-input"
                    minlength="8"
                    required
                >
                <div class="password-match-error" id="password-match-error" style="display: none; color: #ef4444; font-size: 14px; margin-bottom: 12px;">
                    Passwords do not match
                </div>
                <button type="submit" class="password-submit">Complete Setup</button>
            </form>
        </div>
    </div>

    <script>
    document.getElementById('setup-form').addEventListener('submit', function(e) {
        var password = document.getElementById('setup-password').value;
        var confirm = document.getElementById('setup-password-confirm').value;
        var errorEl = document.getElementById('password-match-error');

        if (password !== confirm) {
            e.preventDefault();
            errorEl.style.display = 'block';
            return false;
        }
        errorEl.style.display = 'none';
    });

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
        ctx.font = (size - 4) + 'px serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(emoji, size / 2, size / 2 + 2);

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
            ctx.drawImage(img, 0, 0, size, size);

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

        var letter = null;
        if (showLetter) {
            letter = customLetter || siteName.charAt(0).toUpperCase();
        }

        var options = { letterFont: 'bold 16px sans-serif', padding: 1 };

        if (faviconUrl) {
            setFaviconFromImage(faviconUrl, letter, options);
        } else {
            var emoji = faviconEmoji || siteEmoji || '✅';
            setFaviconFromEmoji(emoji, letter, options);
        }
    });
    </script>
</body>
</html>
