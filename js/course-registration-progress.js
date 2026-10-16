(function () {
    'use strict';
    var tools = document.querySelector('.cr-progress-tools');
    if (!tools) return;
    var search = document.getElementById('cr-progress-search');
    var cards = Array.prototype.slice.call(document.querySelectorAll('#cr-progress-cards .cr-progress-card'));
    var buttons = Array.prototype.slice.call(tools.querySelectorAll('[data-progress-filter]'));
    var mode = 'all';
    function refresh() {
        var query = search.value.trim().toLowerCase();
        var visible = 0;
        cards.forEach(function (card) {
            var matches = card.getAttribute('data-class-name').toLowerCase().indexOf(query) !== -1;
            card.hidden = !(matches && (mode === 'all' || card.getAttribute('data-progress-state') === mode));
            if (!card.hidden) visible++;
        });
        buttons.forEach(function (button) { button.setAttribute('aria-pressed', String(button.getAttribute('data-progress-filter') === mode)); });
        document.getElementById('cr-progress-matches').textContent = visible + ' of ' + cards.length + ' classes shown';
        document.getElementById('cr-progress-empty').hidden = visible !== 0;
    }
    buttons.forEach(function (button) {
        button.addEventListener('click', function () { mode = button.getAttribute('data-progress-filter'); refresh(); });
    });
    search.addEventListener('input', refresh);
    tools.hidden = false;
    refresh();
}());
