/* Test del componente foto condiviso: aggiunta, sostituzione, X,
   annullamento e nessuna cancellazione prima del salvataggio. */
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

function nodo() {
    return {
        hidden: false, textContent: '', src: '', value: '',
        files: [], handlers: {},
        addEventListener(tipo, funzione) { this.handlers[tipo] = funzione; },
        click() { this.cliccato = true; },
        removeAttribute(nome) { if (nome === 'src') this.src = ''; }
    };
}
const selettori = [
    '.menu-foto__input', '.menu-foto__preview', '.menu-foto__preview img',
    '.menu-foto__vuoto', '.menu-foto__scegli', '.menu-foto__annulla',
    '.menu-foto__x', '.menu-foto__messaggio'
];
const elementi = Object.fromEntries(selettori.map(x => [x, nodo()]));
const root = {
    innerHTML: '',
    querySelector: selettore => {
        assert.ok(elementi[selettore], 'Selettore inatteso: ' + selettore);
        return elementi[selettore];
    }
};
const URLFake = {
    createObjectURL: () => 'blob:immagine-di-prova',
    revokeObjectURL: v => assert.equal(v, 'blob:immagine-di-prova')
};
const contesto = {
    document: { getElementById: id => {
        assert.equal(id, 'fotobox-prova');
        return root;
    }},
    BASE: '',
    URL: URLFake
};
vm.createContext(contesto);
vm.runInContext(readFileSync('public/assets/js/menu_foto.js', 'utf8'), contesto);
const foto = vm.runInContext("FotoMenu.crea('fotobox-prova')", contesto);
const campo = elementi['.menu-foto__input'];
const chiudi = elementi['.menu-foto__x'];
const annulla = elementi['.menu-foto__annulla'];
const scegli = elementi['.menu-foto__scegli'];
const anteprima = elementi['.menu-foto__preview'];
assert.ok(root.innerHTML.includes('aria-label="Rimuovi foto"'), 'X accessibile anche senza caratteri visibili');
assert.ok(!root.innerHTML.includes('&times;'), 'La X non deve dipendere dal font');
const css = readFileSync('public/assets/css/app.css', 'utf8');
const estraiRegola = (selettore) => {
    const inizio = css.indexOf(selettore + ' {');
    assert.ok(inizio >= 0, 'Regola CSS mancante: ' + selettore);
    const fine = css.indexOf('}', inizio);
    return css.slice(inizio, fine + 1);
};
const anteprimaCss = estraiRegola('.menu-foto__preview');
const immagineCss = estraiRegola('.menu-foto__preview img');
const pulsanteCss = estraiRegola('.menu-foto__x');
const croceCss = css.slice(css.indexOf('.menu-foto__x::before'), css.indexOf('.menu-foto__x:hover'));
const anteprimaGiornaliera = estraiRegola('.catering-menu-card > img');
const anteprimaCatalogo = estraiRegola('.catering-settimana-foto');
assert.match(anteprimaCss, /width:\s*min\(100%,\s*280px\)/);
assert.match(anteprimaCss, /height:\s*180px/);
assert.match(immagineCss, /width:\s*100%/);
assert.match(immagineCss, /height:\s*100%/);
assert.match(immagineCss, /object-fit:\s*contain/);
assert.match(anteprimaGiornaliera, /width:\s*min\(100%,\s*280px\)/);
assert.match(anteprimaGiornaliera, /height:\s*180px/);
assert.match(anteprimaGiornaliera, /object-fit:\s*contain/);
assert.match(anteprimaCatalogo, /width:\s*110px/);
assert.match(anteprimaCatalogo, /height:\s*78px/);
assert.match(anteprimaCatalogo, /object-fit:\s*contain/);
assert.match(pulsanteCss, /width:\s*44px/);
assert.match(pulsanteCss, /height:\s*44px/);
assert.match(croceCss, /top:\s*50%/);
assert.match(croceCss, /left:\s*50%/);
assert.match(croceCss, /translate\(-50%,\s*-50%\) rotate\(45deg\)/);
assert.match(croceCss, /translate\(-50%,\s*-50%\) rotate\(-45deg\)/);
assert.ok(root.innerHTML.includes('Aggiungi foto'));
assert.equal(foto.haFoto(), false);
assert.equal(anteprima.hidden, true);
scegli.handlers.click();
assert.equal(campo.cliccato, true);

foto.imposta('menu/settimane/originale.jpg');
assert.equal(foto.haFoto(), true);
assert.equal(anteprima.hidden, false);
assert.equal(elementi['.menu-foto__preview img'].src,
    '/storage/menu/settimane/originale.jpg');
assert.equal(scegli.textContent, 'Sostituisci foto');
chiudi.handlers.click();
assert.equal(foto.haFoto(), false);
assert.equal(foto.rimuovere(), true);
assert.equal(anteprima.hidden, true);
assert.equal(annulla.hidden, false);
annulla.handlers.click();
assert.equal(foto.rimuovere(), false);
assert.equal(foto.haFoto(), true);

campo.files = [{ name: 'nuova.png' }];
campo.handlers.change();
assert.equal(foto.file().name, 'nuova.png');
assert.equal(elementi['.menu-foto__preview img'].src, 'blob:immagine-di-prova');
chiudi.handlers.click();
campo.files = [];
assert.equal(foto.rimuovere(), false);
assert.equal(foto.haFoto(), true, 'La X su una nuova foto ripristina quella già pubblicata');

foto.imposta(null);
assert.equal(foto.haFoto(), false);
campo.files = [{ name: 'solo-foto.png' }];
campo.handlers.change();
assert.equal(foto.haFoto(), true);
foto.imposta(null);
campo.files = [];
assert.equal(foto.rimuovere(), false);
assert.equal(foto.haFoto(), false);
console.log('OK: foto uniformate 280x180, miniature 110x78, X centrata e rimozione differita.');
