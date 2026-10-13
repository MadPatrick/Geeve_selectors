(function () {
    // Config > Tegels: tegels slepen om de volgorde te wijzigen (binnen dezelfde groep). De volgorde van de rijen in het
    // formulier (verborgen velden order[groep][]) wordt bij Opslaan bewaard.
    var lists = document.querySelectorAll('.tiles-list');
    var dragging = null;

    Array.prototype.forEach.call(lists, function (list) {
        Array.prototype.forEach.call(list.querySelectorAll('.tiles-row'), function (row) {
            var handle = row.querySelector('.tiles-handle');
            if (!handle) { return; }
            // Alleen slepen via het blokje links, zodat de schakelaars gewoon aan/uit te zetten blijven.
            handle.addEventListener('mousedown', function () { row.draggable = true; });
            handle.addEventListener('mouseup', function () { row.draggable = false; });
            row.addEventListener('dragstart', function (e) {
                dragging = row;
                row.classList.add('is-dragging');
                if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', row.getAttribute('data-id') || ''); }
            });
            row.addEventListener('dragend', function () {
                row.draggable = false;
                row.classList.remove('is-dragging');
                dragging = null;
            });
        });
        list.addEventListener('dragover', function (e) {
            if (!dragging || dragging.parentNode !== list) { return; }
            e.preventDefault();
            var target = e.target.closest ? e.target.closest('.tiles-row') : null;
            if (!target || target === dragging || target.parentNode !== list) { return; }
            var box = target.getBoundingClientRect();
            var after = e.clientY > box.top + box.height / 2;
            list.insertBefore(dragging, after ? target.nextSibling : target);
        });
        list.addEventListener('drop', function (e) { if (dragging) { e.preventDefault(); } });
    });
})();
