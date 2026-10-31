<?php
/*
 * menu_destro.php - Menu/Indice condiviso (icona in alto a destra)
 * Versione: 1.0
 * Data: 2026-10-26
 *
 * USO (dentro <header class="main-header">, al posto del vecchio blocco):
 *     <?php include __DIR__ . '/menu_destro.php'; ?>
 *
 * Solo index.php mostra la voce Admin:
 *     <?php $menu_voce_admin = true; include __DIR__ . '/menu_destro.php'; ?>
 *
 * Il comportamento (apri/chiudi, sotto-menu, auto-chiusura 5s) sta in
 * menu_destro.js, caricato qui sotto: le pagine non devono piu' avere
 * lo script "Toggle menu in alto DX".
 *
 * NOTA: file solo ASCII - per gli accenti usare entita' HTML (&agrave; ecc.)
 */
?>
<!-- Icona Menu/Indice (destra) -->
        <div class="header-menu-container">
            <button class="header-icon right-icon menu-toggle" title="Menu">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
                <span class="icon-label">Menu</span>
            </button>

            <div class="submenu">
                <a href="index.php" class="submenu-item">Home</a>
                <a href="belle.php" class="submenu-item">Diario del cielo</a>
                <div class="submenu-item has-sub">
                    <span class="submenu-item-label">Statistiche &#9658;</span>
                    <div class="sub-submenu">
                        <a href="stat_display.php" class="submenu-item" target="_blank">Tabelle</a>
                        <a href="termo_stat_display.php" class="submenu-item" target="_blank">Termo</a>
                        <a href="pluvio_stat_display.php" class="submenu-item" target="_blank">Pluvio</a>
                    </div>
                </div>
                <a href="grafici_termo_plotly.php?range=24h&amp;visible=" class="submenu-item">Grafici</a>
                <a href="pluvio.php" class="submenu-item">Pioggia: 24h</a>
                <a href="pluvio_tab.php" class="submenu-item">Pioggia: tabella</a>
                <div class="submenu-item has-sub">
                    <span class="submenu-item-label">Previsioni &#9658;</span>
                    <div class="sub-submenu">
                        <a href="previsione_display.php" class="submenu-item" target="_blank">Tecniche: 48 h</a>
                        <a href="previsione_display_sempl.php" class="submenu-item" target="_blank">Sempice: 96 h</a>
                        <a href="https://www.lineameteo.it/modelli_hr.php" class="submenu-item" target="_blank">Mappe (lineameteo.it)</a>
                    </div>
                </div>
<?php if (!empty($menu_voce_admin)): ?>
                <a href="#" class="submenu-item" onclick="window.open('classifica_immagini.php?token=', '_blank'); return false;">Admin</a>
<?php endif; ?>
            </div>
        </div>
        <script src="menu_destro.js?v=1.0" defer></script>
