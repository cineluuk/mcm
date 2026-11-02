(function () {
    var calendar = document.getElementById('calendar');
    var matches = window.MCM_CALENDAR || [];
    if (!calendar) {
        return;
    }

    var byDay = {};
    matches.forEach(function (match) {
        if (!match.day) {
            return;
        }
        if (!byDay[match.day]) {
            byDay[match.day] = [];
        }
        byDay[match.day].push(match);
    });

    var today = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Europe/Zurich',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    }).format(new Date());

    var labelFormat = new Intl.DateTimeFormat('de-CH', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'Europe/Zurich'
    });

    function dateAt(iso) {
        var parts = iso.split('-');
        return new Date(Date.UTC(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]), 10, 0, 0));
    }

    function addDays(iso, amount) {
        var parts = iso.split('-');
        var date = new Date(Date.UTC(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]) + amount, 12, 0, 0));
        return date.toISOString().slice(0, 10);
    }

    function esc(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
        });
    }

    function dayHtml(iso) {
        var date = dateAt(iso);
        var games = byDay[iso] || [];
        var html = '<section class="day' + (games.length ? ' has-match' : '') + (iso === today ? ' is-today' : '') + '" data-date="' + iso + '" id="day-' + iso + '">';
        html += '<div class="day-label">' + esc(labelFormat.format(date)) + '</div>';
        games.forEach(function (match) {
            var body = '<strong>' + esc(match.title) + '</strong>';
            var place = [];
            if (match.venue) {
                place.push(match.venue);
            }
            if (match.time) {
                place.push(match.time);
            }
            if (place.length) {
                body += '<span class="meta">' + esc(place.join(', ')) + '</span>';
            }
            if (match.club) {
                body += '<span class="meta">' + esc(match.club) + '</span>';
            }
            if (match.open) {
                html += '<a class="match" href="/match.php?id=' + Number(match.id) + '">' + body + '</a>';
            } else {
                html += '<div class="match is-locked"><span class="lock" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="10" rx="1"></rect><path d="M8 11V8a4 4 0 0 1 8 0v3"></path></svg></span>' + body + '</div>';
            }
        });
        html += '</section>';
        return html;
    }

    var future = 40;
    var past = 40;
    var html = '';
    for (var offset = future; offset >= -past; offset -= 1) {
        html += dayHtml(addDays(today, offset));
    }
    calendar.innerHTML = html;

    var todayEl = document.getElementById('day-' + today);
    if (todayEl) {
        calendar.scrollTop = todayEl.offsetTop;
    }

    var growing = false;
    calendar.addEventListener('scroll', function () {
        if (growing) {
            return;
        }
        if (calendar.scrollTop < 240 && future < 500) {
            growing = true;
            var first = calendar.firstElementChild.getAttribute('data-date');
            var chunk = '';
            var count = 21;
            for (var i = count; i >= 1; i -= 1) {
                chunk += dayHtml(addDays(first, i));
            }
            var previous = calendar.scrollHeight;
            calendar.insertAdjacentHTML('afterbegin', chunk);
            calendar.scrollTop += calendar.scrollHeight - previous;
            future += count;
            growing = false;
        } else if (calendar.scrollHeight - calendar.scrollTop - calendar.clientHeight < 240 && past < 500) {
            growing = true;
            var last = calendar.lastElementChild.getAttribute('data-date');
            var more = '';
            var extra = 21;
            for (var n = 1; n <= extra; n += 1) {
                more += dayHtml(addDays(last, -n));
            }
            calendar.insertAdjacentHTML('beforeend', more);
            past += extra;
            growing = false;
        }
    });
})();
