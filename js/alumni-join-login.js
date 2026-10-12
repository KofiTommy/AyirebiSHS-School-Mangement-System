(function () {
    'use strict';
    var dialog = document.getElementById('old-student-login');
    // Ordinary sign-in links remain usable without dialog support or JavaScript.
    if (!dialog || typeof dialog.showModal !== 'function') return;
    var lastTrigger = null;
    function openLogin(trigger) {
        if (trigger) lastTrigger = trigger;
        if (!dialog.open) dialog.showModal();
        document.documentElement.classList.add('alumni-login-open');
        document.getElementById('old-student-identity').focus();
    }
    document.querySelectorAll('[data-open-login]').forEach(function (trigger) {
        trigger.hidden = false;
        trigger.setAttribute('aria-haspopup', 'dialog');
        trigger.setAttribute('aria-controls', 'old-student-login');
        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            openLogin(trigger);
        });
    });
    dialog.querySelectorAll('[data-close-login]').forEach(function (button) {
        button.addEventListener('click', function () { dialog.close(); });
    });
    dialog.addEventListener('click', function (event) {
        var bounds = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
    });
    dialog.addEventListener('close', function () {
        document.documentElement.classList.remove('alumni-login-open');
        document.getElementById('old-student-password').value = '';
        if (window.location.hash === '#old-student-login') history.replaceState(null, '', window.location.pathname + window.location.search);
        if (lastTrigger && document.contains(lastTrigger)) lastTrigger.focus();
    });
    window.addEventListener('hashchange', function () {
        if (window.location.hash === '#old-student-login') openLogin();
    });
    if (dialog.dataset.loginError === '1' || window.location.hash === '#old-student-login') openLogin();
})();
