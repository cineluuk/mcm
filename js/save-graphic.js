function saveGraphic(canvas, filename) {
    var dataUrl = canvas.toDataURL('image/jpeg', 0.92);
    var parts = dataUrl.split(',');
    var binary = atob(parts[1]);
    var bytes = new Uint8Array(binary.length);
    for (var i = 0; i < binary.length; i += 1) {
        bytes[i] = binary.charCodeAt(i);
    }
    var file = new File([bytes], filename, { type: 'image/jpeg' });

    function download() {
        var link = document.createElement('a');
        link.href = URL.createObjectURL(file);
        link.download = filename;
        link.click();
    }

    if (navigator.canShare && navigator.canShare({ files: [file] })) {
        navigator.share({ files: [file], title: filename }).catch(function (err) {
            if (!err || err.name !== 'AbortError') {
                download();
            }
        });
        return;
    }

    download();
}
