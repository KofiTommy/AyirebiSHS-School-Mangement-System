<?php
/* Shared colour-mode switcher for the teacher and student dashboards.
   Include once just before </body>. Pairs with colour-modes-head.php. */
if(!defined('COLOUR_MODES_SWITCHER_SENT')){
    define('COLOUR_MODES_SWITCHER_SENT', true);
?>
<div class="admin-theme-switcher" role="group" aria-label="Colour mode">
    <span class="admin-theme-switcher__label">Theme</span>
    <button type="button" data-admin-theme-value="light" title="Light mode" aria-label="Light mode"><i class="admin-theme-dot admin-theme-dot--light"></i></button>
    <button type="button" data-admin-theme-value="dark" title="Dark mode" aria-label="Dark mode"><i class="admin-theme-dot admin-theme-dot--dark"></i></button>
    <button type="button" data-admin-theme-value="classic" title="Classic &mdash; original colours" aria-label="Classic original colours"><i class="admin-theme-dot admin-theme-dot--classic"></i></button>
    <button type="button" data-admin-theme-value="ocean" title="Ocean &mdash; blue palette" aria-label="Ocean blue palette"><i class="admin-theme-dot admin-theme-dot--ocean"></i></button>
    <button type="button" data-admin-theme-value="emerald" title="Emerald &mdash; green palette" aria-label="Emerald green palette"><i class="admin-theme-dot admin-theme-dot--emerald"></i></button>
    <button type="button" data-admin-theme-value="royal" title="Royal &mdash; violet palette" aria-label="Royal violet palette"><i class="admin-theme-dot admin-theme-dot--royal"></i></button>
</div>
<script>
(function () {
    'use strict';
    var KEY = 'admin-colour-mode';
    var root = document.documentElement;
    function sync(v) {
        var btns = document.querySelectorAll('[data-admin-theme-value]');
        for (var i = 0; i < btns.length; i++) {
            btns[i].classList.toggle('is-active', btns[i].getAttribute('data-admin-theme-value') === v);
        }
    }
    function apply(v) {
        root.setAttribute('data-admin-theme', v);
        sync(v);
    }

    var saved = null;
    try { saved = localStorage.getItem(KEY); } catch (e) {}
    var validModes = { light: 1, dark: 1, classic: 1, ocean: 1, emerald: 1, royal: 1 };
    if (!validModes[saved]) { saved = 'light'; }
    apply(saved);

    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-admin-theme-value]') : null;
        if (!btn) { return; }
        var v = btn.getAttribute('data-admin-theme-value');
        try { localStorage.setItem(KEY, v); } catch (err) {}
        apply(v);
    });

})();
</script>
<?php
}
