<?php
/* Shared colour-mode theme for the teacher and student dashboards.
   Include once inside <head>, AFTER the page's own stylesheets. */
if(!defined('COLOUR_MODES_HEAD_SENT')){
    define('COLOUR_MODES_HEAD_SENT', true);
?>
<link rel="stylesheet" type="text/css" href="css/colour-modes.css">
<script>
(function () {
    try {
        var v = localStorage.getItem('admin-colour-mode');
        var themes = { light: 1, dark: 1, classic: 1, ocean: 1, emerald: 1, royal: 1 };
        var resolved = v;
        if (!themes[resolved]) { resolved = 'light'; }
        document.documentElement.setAttribute('data-admin-theme', resolved);
    } catch (e) { document.documentElement.setAttribute('data-admin-theme', 'light'); }
})();
</script>
<?php
}
