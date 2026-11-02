(function () {
    var config = window.MCM_LINEUP;
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

    function filled(prefix, count) {
        var names = [];
        for (var i = 0; i < count; i += 1) {
            var value = document.getElementById(prefix + i).value.trim();
            if (value) {
                names.push(value.toUpperCase());
            }
        }
        return names;
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

    function fitFont(text, size, maxWidth, minSize) {
        ctx.font = size + 'px "Bebas Neue", Arial, sans-serif';
        while (size > minSize && ctx.measureText(text).width > maxWidth) {
            size -= 2;
            ctx.font = size + 'px "Bebas Neue", Arial, sans-serif';
        }
        return size;
    }

    function draw() {
        if (!img.complete || img.naturalWidth === 0) {
            return;
        }

        var starters = filled('p', 11);
        var subs = filled('s', 7);
        var frame = coverSize();
        ctx.fillStyle = '#111111';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, frame.x, frame.y, frame.width, frame.height);

        var shade = ctx.createLinearGradient(0, 0, canvas.width * 0.78, 0);
        shade.addColorStop(0, 'rgba(17,17,17,0.9)');
        shade.addColorStop(1, 'rgba(17,17,17,0)');
        ctx.fillStyle = shade;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';
        var badge = 'AUFSTELLUNG';
        ctx.font = '72px "Bebas Neue", Arial, sans-serif';
        var badgeWidth = ctx.measureText(badge).width + 48;
        ctx.fillStyle = '#f5c400';
        ctx.fillRect(48, 48, badgeWidth, 92);
        ctx.fillStyle = '#141414';
        ctx.fillText(badge, 72, 94);

        ctx.fillStyle = '#f3ead2';
        ctx.font = '36px "Bebas Neue", Arial, sans-serif';
        ctx.fillText(config.match.toUpperCase(), 48, 188);

        var top = 250;
        var bottom = canvas.height - 56;
        var section = subs.length ? 52 : 0;
        var room = Math.max(bottom - top - section, 1);
        var starterShare = starters.length && subs.length ? 0.72 : 1;
        var starterLine = starters.length ? Math.min(78, (room * starterShare) / starters.length) : 0;
        var subRoom = room - starterLine * starters.length;
        var subLine = subs.length ? Math.min(46, subRoom / subs.length) : 0;
        var maxWidth = canvas.width - 120;
        var y = top;

        starters.forEach(function (name) {
            var size = fitFont(name, Math.max(34, Math.floor(starterLine - 8)), maxWidth, 28);
            ctx.fillStyle = '#f5c400';
            ctx.fillText(name, 56, y + starterLine / 2);
            y += starterLine;
        });

        if (subs.length) {
            y += 8;
            ctx.fillStyle = '#f3ead2';
            ctx.font = '32px "Bebas Neue", Arial, sans-serif';
            ctx.fillText('AUSWECHSEL', 56, y + 20);
            y += section;
            subs.forEach(function (name) {
                fitFont(name, Math.max(22, Math.floor(subLine - 6)), maxWidth, 18);
                ctx.fillStyle = '#f3ead2';
                ctx.fillText(name, 56, y + subLine / 2);
                y += subLine;
            });
        }
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

    document.getElementById('zoom').addEventListener('input', function (event) {
        scale = Number(event.target.value) / 100;
        draw();
    });

    function watch(prefix, count) {
        for (var i = 0; i < count; i += 1) {
            document.getElementById(prefix + i).addEventListener('input', draw);
        }
    }
    watch('p', 11);
    watch('s', 7);

    document.getElementById('save').addEventListener('click', function () {
        saveGraphic(canvas, 'aufstellung.jpg');
    });

    img.onload = function () {
        document.fonts.ready.then(draw);
    };
    img.src = config.photo;
})();
