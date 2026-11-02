(function () {
    var config = window.MCM_GOAL;
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

        var bar = 340;
        var gradient = ctx.createLinearGradient(0, canvas.height - bar, 0, canvas.height);
        gradient.addColorStop(0, 'rgba(17,17,17,0)');
        gradient.addColorStop(1, 'rgba(17,17,17,0.92)');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, canvas.height - bar, canvas.width, bar);

        ctx.fillStyle = '#f5c400';
        ctx.fillRect(48, 48, 220, 92);
        ctx.fillStyle = '#141414';
        ctx.font = '72px "Bebas Neue", Arial, sans-serif';
        ctx.textBaseline = 'middle';
        ctx.fillText('TOR', 78, 94);

        var scorer = text('scorer') || 'TORSCHÜTZE';
        var minute = text('minute');
        var score = (text('home') || '0') + ' : ' + (text('away') || '0');

        ctx.fillStyle = '#f5c400';
        ctx.font = '150px "Bebas Neue", Arial, sans-serif';
        ctx.fillText(score, 48, canvas.height - 210);

        ctx.fillStyle = '#f7f7f7';
        ctx.font = '78px "Bebas Neue", Arial, sans-serif';
        ctx.fillText(scorer.toUpperCase(), 48, canvas.height - 110);

        ctx.fillStyle = '#f3ead2';
        ctx.font = '42px "Bebas Neue", Arial, sans-serif';
        var meta = minute !== '' ? minute + "'  ·  " + config.match : config.match;
        ctx.fillText(meta.toUpperCase(), 48, canvas.height - 52);
    }

    function point(event) {
        var rect = canvas.getBoundingClientRect();
        var touch = event.touches ? event.touches[0] : event;
        return {
            x: (touch.clientX - rect.left) * (canvas.width / rect.width),
            y: (touch.clientY - rect.top) * (canvas.height / rect.height)
        };
    }

    function startDrag(event) {
        var p = point(event);
        drag = { x: p.x - panX, y: p.y - panY };
        if (canvas.setPointerCapture) {
            canvas.setPointerCapture(event.pointerId);
        }
    }

    function moveDrag(event) {
        if (!drag) {
            return;
        }
        event.preventDefault();
        var p = point(event);
        panX = p.x - drag.x;
        panY = p.y - drag.y;
        draw();
    }

    function endDrag() {
        drag = null;
    }

    canvas.addEventListener('pointerdown', startDrag);
    canvas.addEventListener('pointermove', moveDrag);
    canvas.addEventListener('pointerup', endDrag);
    canvas.addEventListener('pointercancel', endDrag);

    ['scorer', 'minute', 'home', 'away'].forEach(function (id) {
        document.getElementById(id).addEventListener('input', draw);
    });

    document.getElementById('zoom').addEventListener('input', function (event) {
        scale = Number(event.target.value) / 100;
        draw();
    });

    document.getElementById('save').addEventListener('click', function () {
        saveGraphic(canvas, 'tor.jpg');
    });

    img.onload = function () {
        document.fonts.ready.then(draw);
    };
    img.src = config.photo;
})();
