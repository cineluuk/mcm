<?php
declare(strict_types=1);

$dir = __DIR__ . '/';

$images = array_merge(
    glob($dir . '*.jpg'),
    glob($dir . '*.JPG'),
    glob($dir . '*.jpeg'),
    glob($dir . '*.JPEG')
);

usort($images, function ($a, $b) {
    return filemtime($b) <=> filemtime($a);
});
?>
<!doctype html>
<html lang="de-CH">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cineluuk Gallery</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px;
            font-family: Arial, sans-serif;
            background: #111;
            color: white;
        }

        h1 {
            text-align: center;
            margin: 0 0 24px 0;
            font-size: 32px;
        }

        .gallery-wrapper {
            max-width: 1600px;
            margin: 0 auto;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
        }

        .item {
            background: #1b1b1b;
            border-radius: 6px;
            overflow: hidden;
        }

        .item img {
            width: 100%;
            height: auto;
            display: block;
        }

        .empty {
            text-align: center;
            color: #bbb;
            margin-top: 40px;
            font-size: 18px;
        }

        @media (max-width: 900px) {
            .grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 700px) {
            body {
                padding: 16px;
            }

            h1 {
                font-size: 26px;
                margin-bottom: 18px;
            }

            .grid {
                grid-template-columns: 1fr;
                gap: 14px;
            }
        }
    </style>
</head>
<body>

<div class="gallery-wrapper">
    <h1>Cineluuk Gallery</h1>

    <?php if (empty($images)): ?>
        <div class="empty">Noch keine Bilder vorhanden.</div>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($images as $img): ?>
                <?php $filename = basename($img); ?>

                <div class="item">
                    <img 
                        src="<?= htmlspecialchars(rawurlencode($filename)) ?>" 
                        alt="" 
                        loading="lazy"
                    >
                </div>

            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script>
setTimeout(() => {
    window.location.reload();
}, 10000);
</script>
</body>
</html>