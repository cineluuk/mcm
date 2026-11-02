(function () {
    var config = window.MCM_SCORE;
    var canvas = document.getElementById('preview');
    if (!canvas || !config) {
        return;
    }

    var ctx = canvas.getContext('2d');
    var img = new Image();
    var scale = 1;
    var panX = 0;
    var panY = 0;
    var drag = null;

    function text(id) {
        var el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    function coverSize() {
        var canvasRatio = canvas.width / canvas.height;
        var imageRatio = img.width / img.height;
        var width;
        var height;
        if (imageRatio > canvasRatio) {
            height = canvas.height * scale;
            width = height * imageRatio;
        } else {
            width = canvas.width * scale;
            height = width / imageRatio;
        }
        return {
            width: width,
            height: height,
            x: (canvas.width - width) / 2 + panX,
            y: (canvas.height - height) / 2 + panY
        };
    }

    function draw() {
        if (!img.complete || img.naturalWidth === 0) {
            return;
        }

        var frame = coverSize();
        ctx.fillStyle = '#111111';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, frame.x, frame.y, frame.width, frame.height);

        var gradient = ctx.createLinearGradient(0, canvas.height - 420, 0, canvas.height);
        gradient.addColorStop(0, 'rgba(17,17,17,0)');
        gradient.addColorStop(1, 'rgba(17,17,17,0.92)');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, canvas.height - 420, canvas.width, 420);

        ctx.font = '72px "Bebas Neue", Arial, sans-serif';
        ctx.textBaseline = 'middle';
        var label = config.badge;
        var badgeWidth = ctx.measureText(label).width + 56;
        ctx.fillStyle = '#f5c400';
        ctx.fillRect(48, 48, badgeWidth, 92);
        ctx.fillStyle = '#141414';
        ctx.fillText(label, 76, 94);

        var score = (text('home') || '0') + ' : ' + (text('away') || '0');
        ctx.fillStyle = '#f5c400';
        ctx.font = '180px "Bebas Neue", Arial, sans-serif';
        ctx.fillText(score, 48, canvas.height - 180);

        ctx.fillStyle = '#f3ead2';
        ctx.font = '42px "Bebas Neue", Arial, sans-serif';
        ctx.fillText(config.match.toUpperCase(), 48, canvas.height - 70);
    }

    function point(event) {
        var rect = canvas.getBoundingClientRect();
        return {
            x: (event.clientX - rect.left) * (canvas.width / rect.width),
            y: (event.clientY - rect.top) * (canvas.height / rect.height)
        };
    }

    canvas.addEventListener('pointerdown', function (event) {
        var p = point(event);
        drag = { x: p.x - panX, y: p.y - panY };
        canvas.setPointerCapture(event.pointerId);
    });
    canvas.addEventListener('pointermove', function (event) {
        if (!drag) {
            return;
        }
        event.preventDefault();
        var p = point(event);
        panX = p.x - drag.x;
        panY = p.y - drag.y;
        draw();
    });
    canvas.addEventListener('pointerup', function () { drag = null; });
    canvas.addEventListener('pointercancel', function () { drag = null; });

    ['home', 'away'].forEach(function (id) {
        document.getElementById(id).addEventListener('input', draw);
    });
    document.getElementById('zoom').addEventListener('input', function (event) {
        scale = Number(event.target.value) / 100;
        draw();
    });
    document.getElementById('save').addEventListener('click', function () {
        saveGraphic(canvas, config.file);
    });

    img.onload = function () {
        document.fonts.ready.then(draw);
    };
    img.src = config.photo;
})();
