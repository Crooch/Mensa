/* Presentazione delle tabelle su schermi piccoli per Genitore, Operatore e Admin.
 * Le celle conservano il loro contenuto e la semantica tabellare; ogni valore
 * riceve l'etichetta della propria colonna per la vista mobile.
 * Aggiornamento automatico anche dopo ricerche e rendering AJAX. */
(() => {
    if (!['genitore', 'operatore', 'admin'].includes(document.body.dataset.ruolo)) return;
    const principale = document.querySelector('main');
    if (!principale) return;
    const aggiornaEtichette = () => {
        principale.querySelectorAll('.tabella-scroll table').forEach(tabella => {
            const titoli = Array.from(tabella.querySelectorAll('thead th'),
                (th) => th.textContent.trim());
            if (!titoli.length) return;
            tabella.querySelectorAll('tbody tr').forEach(riga => {
                Array.from(riga.children).forEach((cella, indice) => {
                    if (cella.tagName !== 'TD' || cella.hasAttribute('colspan')) return;
                    const titolo = titoli[indice] || 'Azioni';
                    if (cella.dataset.etichetta !== titolo) {
                        cella.dataset.etichetta = titolo;
                    }
                });
            });
        });
    };
    aggiornaEtichette();
    if (typeof MutationObserver !== 'undefined') {
        // Osserviamo solo nuovi nodi: le modifiche a data-etichetta non
        // generano altre notifiche e non ricreano listener o contenuti.
        const osservatore = new MutationObserver(aggiornaEtichette);
        osservatore.observe(principale, { childList: true, subtree: true });
    }
})();
