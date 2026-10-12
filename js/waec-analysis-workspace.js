(function () {
    'use strict';
    var workspace = document.querySelector('[data-waec-workspace]');
    if (!workspace) return;
    var panels = Array.prototype.slice.call(workspace.querySelectorAll('.waec-panel'));
    var links = Array.prototype.slice.call(workspace.querySelectorAll('[data-waec-nav]'));

    function openSection(scroll) {
        var hash;
        try { hash = decodeURIComponent(window.location.hash.slice(1)); } catch (error) { hash = ''; }
        var target = hash ? document.getElementById(hash) : null;
        var active = target ? target.closest('.waec-panel') : null;
        if (!active) {
            var query = new URLSearchParams(window.location.search);
            var studentSearch = query.get('student_query') || query.get('student_key');
            active = document.getElementById(studentSearch ? 'student-result-analysis' : workspace.getAttribute('data-default-section'));
        }
        if (!active) return;
        panels.forEach(function (panel) { panel.hidden = panel !== active; });
        links.forEach(function (link) {
            if (link.hash === '#' + active.id) link.setAttribute('aria-current', 'location');
            else link.removeAttribute('aria-current');
        });
        if (target) {
            var parent = target.parentElement;
            while (parent && parent !== workspace) {
                if (parent.tagName === 'DETAILS') parent.open = true;
                parent = parent.parentElement;
            }
        }
        if (scroll && target) {
            target.scrollIntoView({block: 'start'});
            target.setAttribute('tabindex', '-1');
            target.focus({preventScroll: true});
        }
    }
    window.addEventListener('hashchange', function () { openSection(true); });
    workspace.addEventListener('click', function (event) {
        var link = event.target.closest('a[href^="#"]');
        if (link && link.hash === window.location.hash) openSection(true);
    });
    workspace.querySelectorAll('.waec-table').forEach(function (table) {
        var wrapper = table.parentElement;
        if (!wrapper.classList.contains('waec-table-wrap')) {
            wrapper = document.createElement('div');
            table.parentElement.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
        wrapper.classList.add('waec-table-scroll');
        wrapper.setAttribute('tabindex', '0');
        wrapper.setAttribute('role', 'region');
        var heading = table.closest('.waec-card').querySelector('h2, h3');
        wrapper.setAttribute('aria-label', heading ? heading.textContent.trim() + ' table' : 'Results table');
    });
    openSection(false);
    // Printing the whole workspace includes every section, including folded details.
    var folded = [];
    window.addEventListener('beforeprint', function () {
        folded = Array.prototype.slice.call(workspace.querySelectorAll('details:not([open])'));
        folded.forEach(function (detail) { detail.open = true; });
    });
    window.addEventListener('afterprint', function () {
        folded.forEach(function (detail) { detail.open = false; });
        folded = [];
    });
})();
