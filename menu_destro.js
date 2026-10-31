/*
 * menu_destro.js - Comportamento del menu condiviso (menu_destro.php)
 * Versione: 1.0
 * Data: 2026-10-26
 *
 * Sostituisce lo script "Toggle menu in alto DX" duplicato in ogni pagina.
 * Logica invariata: apertura/chiusura, sotto-menu .has-sub,
 * auto-chiusura dopo 5 s, chiusura cliccando fuori.
 */
document.addEventListener('DOMContentLoaded', function () {
    var menuToggle = document.querySelector('.menu-toggle');
    var submenu = document.querySelector('.submenu');
    if (!menuToggle || !submenu) return;

    // Protezione: se per errore una pagina conserva anche il vecchio
    // script, non registra due volte gli eventi (si annullerebbero).
    if (menuToggle.dataset.menuInit === '1') return;
    menuToggle.dataset.menuInit = '1';

    var autoCloseTimer = null;

    function openMenu() {
        submenu.classList.add('active');
        clearTimeout(autoCloseTimer);
        autoCloseTimer = setTimeout(closeMenu, 5000);
    }

    function closeMenu() {
        submenu.classList.remove('active');
        clearTimeout(autoCloseTimer);
        // chiudi anche eventuali sub-submenu aperti
        document.querySelectorAll('.has-sub.sub-active').forEach(function (el) {
            el.classList.remove('sub-active');
        });
    }

    menuToggle.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (submenu.classList.contains('active')) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    // Gestione sub-submenu (click su .has-sub)
    document.querySelectorAll('.has-sub').forEach(function (item) {
        item.addEventListener('click', function (e) {
            e.stopPropagation();
            // resetta timer auto-close: l'utente sta interagendo
            clearTimeout(autoCloseTimer);
            autoCloseTimer = setTimeout(closeMenu, 5000);
            item.classList.toggle('sub-active');
        });
    });

    // Chiudi tutto cliccando fuori
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.header-menu-container')) {
            closeMenu();
        }
    });
});
