/* Componente foto condiviso fra menu del giorno e giorni settimanali.
 * Le operazioni agiscono sulla bozza: nessun upload/rimozione prima di Salva. */
const FotoMenu = {
    crea(id) {
        const root = document.getElementById(id);
        if (!root) throw new Error('Campo foto non trovato: ' + id);
        root.innerHTML = `
            <div class="menu-foto__preview" hidden>
                <img alt="Anteprima della foto del menu">
                <button type="button" class="menu-foto__x"
                        aria-label="Rimuovi foto" title="Rimuovi foto"></button>
            </div>
            <div class="menu-foto__vuoto">Nessuna foto</div>
            <div class="menu-foto__comandi">
                <button type="button" class="btn btn-outline-primary menu-foto__scegli">Aggiungi foto</button>
                <button type="button" class="btn btn-link menu-foto__annulla" hidden>Annulla rimozione</button>
            </div>
            <input type="file" id="${id}-input" class="menu-foto__input" accept="image/png,image/jpeg,image/webp"
                   aria-label="Seleziona una foto del menu" hidden>
            <p class="menu-foto__messaggio" role="status" aria-live="polite"></p>`;
        const campo = root.querySelector('.menu-foto__input');
        const anteprima = root.querySelector('.menu-foto__preview');
        const img = root.querySelector('.menu-foto__preview img');
        const vuoto = root.querySelector('.menu-foto__vuoto');
        const scegli = root.querySelector('.menu-foto__scegli');
        const annulla = root.querySelector('.menu-foto__annulla');
        const chiudi = root.querySelector('.menu-foto__x');
        const messaggio = root.querySelector('.menu-foto__messaggio');
        let salvata = null;
        let inRimozione = false;
        let temporanea = null;

        function liberaAnteprima() {
            if (temporanea) {
                URL.revokeObjectURL(temporanea);
                temporanea = null;
            }
        }
        function mostra() {
            const selezionata = campo.files && campo.files.length > 0;
            const visibile = selezionata || (!!salvata && !inRimozione);
            anteprima.hidden = !visibile;
            vuoto.hidden = visibile;
            annulla.hidden = !inRimozione;
            scegli.textContent = visibile ? 'Sostituisci foto' : 'Aggiungi foto';
            if (selezionata) {
                if (!temporanea) temporanea = URL.createObjectURL(campo.files[0]);
                img.src = temporanea;
                messaggio.textContent = 'Nuova foto selezionata. Sarà salvata quando confermi il menu.';
            } else if (visibile) {
                liberaAnteprima();
                img.src = BASE + '/storage/' + salvata;
                messaggio.textContent = '';
            } else {
                liberaAnteprima();
                img.removeAttribute('src');
                messaggio.textContent = inRimozione
                    ? 'Foto da rimuovere. La modifica sarà effettiva soltanto dopo il salvataggio.'
                    : '';
            }
        }
        scegli.addEventListener('click', () => campo.click());
        campo.addEventListener('change', () => {
            if (campo.files.length) {
                inRimozione = false;
                liberaAnteprima();
            }
            mostra();
        });
        chiudi.addEventListener('click', () => {
            if (campo.files.length) {
                campo.value = '';
                liberaAnteprima();
                inRimozione = false;
            } else if (salvata) {
                inRimozione = true;
            }
            mostra();
        });
        annulla.addEventListener('click', () => {
            inRimozione = false;
            mostra();
        });
        function imposta(percorso) {
            campo.value = '';
            liberaAnteprima();
            salvata = percorso || null;
            inRimozione = false;
            mostra();
        }
        function abilita(attivo) {
            campo.disabled = !attivo;
            scegli.disabled = !attivo;
            chiudi.disabled = !attivo;
            annulla.disabled = !attivo;
        }
        imposta(null);
        return {
            imposta,
            abilita,
            file: () => campo.files[0] || null,
            rimuovere: () => inRimozione,
            haFoto: () => Boolean(campo.files.length || (salvata && !inRimozione)),
            distruggi: liberaAnteprima
        };
    }
};
