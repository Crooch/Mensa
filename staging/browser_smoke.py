#!/usr/bin/env python3
"""Collaudo browser sullo staging sintetico: nessuna connessione alla produzione.

Esecuzione sul runner:
  python staging/browser_smoke.py
Richiede: playwright e Chromium. Base consentita solo localhost.
"""
import base64
import json
import os
from pathlib import Path
from urllib.parse import urlparse

from playwright.sync_api import sync_playwright

BASE = os.environ.get("MENSA_BROWSER_BASE", "http://127.0.0.1:18080").rstrip("/")
if urlparse(BASE).hostname not in ("127.0.0.1", "localhost"):
    raise SystemExit("Browser smoke consentito soltanto su localhost")

PASSWORD = "Collaudo2026!"
OUTPUT = Path("staging/browser-artifacts")
OUTPUT.mkdir(parents=True, exist_ok=True)
RUOLI = {
    "admin": "admin.staging",
    "operatore": "operatore.staging",
    "scuola": "scuola.infanzia.staging",
    "catering": "catering.staging",
    "genitore": "genitore.staging",
}
DIMENSIONI = (375, 768, 1366)
PNG_PICCOLO = base64.b64decode(
    "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg=="
)

def controlla_dimensioni(page, contesto):
    dimensioni = page.evaluate("""() => ({
        viewport: window.innerWidth,
        documento: document.documentElement.scrollWidth,
        body: document.body.scrollWidth
    })""")
    reale = max(dimensioni["documento"], dimensioni["body"])
    assert reale <= dimensioni["viewport"] + 3, (
        f"{contesto}: overflow orizzontale {reale}px / viewport {dimensioni['viewport']}px"
    )

def controlla_etichette(page, contesto):
    """Verifica l'associazione reale DOM label.control, non solo il markup."""
    difetti = page.evaluate("""() => Array.from(document.querySelectorAll('label'))
        .filter(label => {
            const controllo = label.control;
            return !controllo ||
                !['INPUT', 'SELECT', 'TEXTAREA'].includes(controllo.tagName) ||
                (label.htmlFor && controllo.id !== label.htmlFor);
        })
        .map(label => ({
            testo: label.textContent.trim().slice(0, 80),
            destinazione: label.htmlFor || '(assente)'
        }))
    """)
    assert not difetti, f"{contesto}: etichette senza campo associato: {difetti}"


def controlla_profili_riordinati(page, ruolo, larghezza):
    """Verifica i nuovi componenti sui tre profili senza modificare i dati."""
    if ruolo not in ("genitore", "operatore", "admin"):
        return
    page.locator('nav.schede [data-vista]').first.click()
    page.locator('.vista:visible .area-intro h1').first.wait_for()
    assert page.locator('.vista:visible .area-intro h1').count() == 1
    if larghezza <= 760:
        nav = page.locator('nav.schede')
        assert nav.evaluate("(e) => getComputedStyle(e).display") == "flex", (
            'Le schede di navigazione devono occupare una sola riga mobile'
        )

    if ruolo == "genitore":
        page.locator('#vista-riepilogo .area-sezione-testa').first.wait_for()
        assert page.locator('#crediti-cards .card-mensa').count() > 0
        page.screenshot(path=str(OUTPUT / f"genitore-riepilogo-nuovo-{larghezza}.png"),
                        full_page=True)
        page.locator('nav.schede [data-vista="calendario"]').click()
        page.locator('#calendario .giorno[data-data]').first.wait_for()
        griglia = page.locator('.famiglia-calendario-layout')
        colonne = griglia.evaluate("(e) => getComputedStyle(e).gridTemplateColumns.split(' ').length")
        assert colonne == (1 if larghezza <= 980 else 2), (
            f'Calendario famiglia: colonne {colonne} non adeguate a {larghezza}px'
        )
        page.screenshot(path=str(OUTPUT / f"genitore-calendario-nuovo-{larghezza}.png"),
                        full_page=True)
        page.locator('nav.schede [data-vista="pagamenti"]').click()
        page.locator('#movimenti-corpo tr').first.wait_for()
        controlla_tabelle_mobili(page, larghezza)
    elif ruolo == "operatore":
        page.locator('#ricerca-famiglie').fill('Fittizio')
        page.locator('#btn-cerca').click()
        page.locator('#famiglie-corpo [data-apri]').first.wait_for()
        page.locator('#famiglie-corpo [data-apri]').first.click()
        page.locator('#btn-acquisto').wait_for(state="visible")
        assert page.locator('#scheda-famiglia .operatore-contatti').count() == 1
        assert page.locator('#scheda-famiglia .operatore-storico').count() == 2
        assert page.locator('#scheda-famiglia .operatore-storico[open]').count() == 0
        layout = page.locator('.operatore-layout')
        colonne = layout.evaluate("(e) => getComputedStyle(e).gridTemplateColumns.split(' ').length")
        assert colonne == (1 if larghezza <= 980 else 2), (
            f'Operatore: colonne {colonne} non adeguate a {larghezza}px'
        )
        if larghezza <= 760:
            assert page.locator('#famiglie-corpo tr').first.evaluate(
                "(e) => getComputedStyle(e).display") in ('flex', 'block')
        page.screenshot(path=str(OUTPUT / f"operatore-famiglia-nuova-{larghezza}.png"),
                        full_page=True)
        page.locator('#scheda-famiglia .operatore-storico').first.locator('summary').click()
        assert page.locator('#scheda-famiglia .operatore-storico[open]').count() == 1
        controlla_tabelle_mobili(page, larghezza)
    else:
        page.locator('#vista-plessi .admin-form-titolo').first.wait_for()
        assert page.locator('#vista-plessi .area-sezione-testa').count() == 1
        page.locator('#plessi-corpo tr').first.wait_for()
        page.screenshot(path=str(OUTPUT / f"admin-plessi-nuovo-{larghezza}.png"),
                        full_page=True)
        controlla_tabelle_mobili(page, larghezza)

    controlla_etichette(page, ruolo + ' nuova interfaccia')
    controlla_dimensioni(page, f"{ruolo} nuova interfaccia @{larghezza}")
    print(f"OK: nuovo layout {ruolo}@{larghezza}, etichette, tabelle e dimensioni")


def controlla_tabelle_mobili(page, larghezza):
    if larghezza > 760:
        return
    celle = page.locator('.vista:visible .tabella-scroll table tbody td:not([colspan])')
    if celle.count():
        page.wait_for_function("""() => {
            const celle = [...document.querySelectorAll('.vista:not([hidden]) .tabella-scroll table tbody td:not([colspan])')];
            return celle.length > 0 && celle.every(c => c.dataset.etichetta);
        }""", timeout=8000)
        assert celle.first.evaluate("(e) => getComputedStyle(e).display") in ('grid', 'block')


def controlla_modali_operatore(page):
    """Tutti i moduli sono aperti ma mai inviati: nessuna mutazione DB."""
    page.locator('[data-vista="famiglie"]').click()
    page.locator('#btn-nuovo-genitore').click()
    page.locator('[data-testid="genitore-form"]').wait_for(state="visible")
    controlla_etichette(page, 'Operatore / nuova famiglia')
    assert page.locator('#modale [data-testid="genitore-form"] > .campo-filtro').count() == 6
    assert page.locator('#modale [data-testid="genitore-form"]').evaluate(
        "(e) => getComputedStyle(e).gridTemplateColumns.split(' ').length") == 2
    page.locator('#modale-chiudi').click()

    # Il seed contiene famiglie fittizie; apriamo solo una scheda.
    page.locator('#ricerca-famiglie').fill('Fittizio')
    page.locator('#btn-cerca').click()
    page.locator('#famiglie-corpo [data-apri]').first.wait_for(state="visible")
    page.locator('#famiglie-corpo [data-apri]').first.click()
    page.locator('#btn-acquisto').wait_for(state="visible")

    moduli = [
        ('#btn-acquisto', 'acquisto-form'),
        ('#btn-override', 'override-form'),
        ('#btn-rettifica', 'rettifica-form'),
        ('#btn-modifica-genitore', 'modifica-genitore-form'),
        ('#btn-nuovo-figlio', 'bambino-form'),
        ('[data-cambio-classe]', 'cambio-classe-form'),
        ('[data-dieta]', 'dieta-form'),
    ]
    for pulsante, form in moduli:
        page.locator('#scheda-famiglia ' + pulsante).first.click()
        page.locator(f'#modale [data-testid="{form}"]').wait_for(state="visible")
        controlla_etichette(page, 'Operatore / ' + form)
        page.locator('#modale-chiudi').click()
    print("OK: 8 moduli Operatore senza label orfane (nessun salvataggio).")


def controlla_modale_mobile(page):
    """Un modulo completo su 375px, Escape e focus, senza salvataggi."""
    page.locator('nav.schede [data-vista="famiglie"]').click()
    page.locator('#btn-nuovo-genitore').click()
    page.locator('#modale [data-testid="genitore-form"]').wait_for(state="visible")
    assert page.locator('#modale [data-testid="genitore-form"] > .campo-filtro').count() == 6
    assert page.locator('#modale [data-testid="genitore-form"]').evaluate(
        "(e) => getComputedStyle(e).gridTemplateColumns.split(' ').length") == 1
    assert page.locator('#op-genitore-nome-input').evaluate("(e) => document.activeElement === e")
    controlla_etichette(page, 'Operatore / modulo mobile')
    controlla_dimensioni(page, 'Operatore / modulo mobile')
    page.screenshot(path=str(OUTPUT / 'operatore-modale-nuovo-375.png'), full_page=True)
    page.keyboard.press('Escape')
    assert page.locator('#modale').is_hidden()
    assert page.locator('#btn-nuovo-genitore').evaluate("(e) => document.activeElement === e")
    print('OK: Operatore modulo mobile, layout singola colonna, focus ed Escape')


def controlla_foto(page):
    # Il test non salva file: utilizza la bozza locale della foto nel form.
    page.locator('[data-vista="menu"]').click()
    page.locator('#vista-menu').wait_for(state="visible")
    # Dal giorno seguente il cutoff di oggi non limita la programmazione.
    from datetime import date, timedelta
    oggi = date.today()
    prossimo_lunedi = oggi + timedelta(days=(7 - oggi.weekday()) % 7 or 7)
    data_menu = max(prossimo_lunedi, date(2026, 10, 19)).isoformat()
    page.locator('#menu-data').fill(data_menu)
    # Aspettare il completamento della richiesta /api/catering/menu:
    # altrimenti il caricamento asincrono azzera la foto appena selezionata.
    page.wait_for_function("""() => {
        const bottone = document.querySelector('#menu-submit');
        return bottone && !bottone.disabled;
    }""", timeout=10000)
    elemento = page.locator('#menu-foto-widget .menu-foto__input')
    elemento.set_input_files({
        "name": "immagine-test.png", "mimeType": "image/png", "buffer": PNG_PICCOLO
    })
    preview = page.locator('#menu-foto-widget .menu-foto__preview')
    preview.wait_for(state="visible")
    cornice = preview.bounding_box()
    assert cornice and abs(cornice["height"] - 180) <= 2, f"Anteprima non standard: {cornice}"
    assert cornice["width"] <= 281, f"Anteprima troppo larga: {cornice}"
    pulsante = page.locator('#menu-foto-widget .menu-foto__x')
    rettangolo = pulsante.bounding_box()
    assert rettangolo and abs(rettangolo["width"] - 44) <= 2, (
        f"Pulsante rimozione non standard: {rettangolo}"
    )
    # La croce geometrica e' collocata dalle pseudo-classi CSS, non dai font.
    assert page.evaluate("""() => {
      const x = document.querySelector('#menu-foto-widget .menu-foto__x');
      return getComputedStyle(x, '::before').content !== 'none'
          && getComputedStyle(x, '::after').content !== 'none';
    }""")
    pulsante.click()
    assert preview.is_hidden(), "La X deve agire sulla bozza, senza inviare il form"
    print("OK: Catering: anteprima 280×180, X geometrica e rimozione in bozza")

def controlla_pagine_variazioni(page, larghezza):
    """23 variazioni sintetiche: 10 per pagina, date decrescenti e nessun overflow."""
    from urllib.parse import parse_qs, urlsplit
    variazioni = [{
        "id": 500 + i, "plesso_id": 1, "tipologia_menu_id": 1,
        "data": f"2030-11-{i:02d}", "plesso_nome": "Plesso di prova",
        "tipologia_nome": "Standard", "descrizione": "Piatto " + str(i),
        "modificabile": True, "ha_modello_settimanale": False
    } for i in range(1, 24)]
    variazioni.sort(key=lambda v: v["data"], reverse=True)

    def risposta_pagina(route):
        richiesta = int(parse_qs(urlsplit(route.request.url).query)["pagina"][0])
        pagine = 3
        pagina = max(1, min(richiesta, pagine))
        risposta = {
            "righe": variazioni[(pagina - 1) * 10:pagina * 10],
            "pagina": pagina, "per_pagina": 10, "totale": 23, "pagine": pagine
        }
        route.fulfill(status=200, content_type="application/json",
                      body=json.dumps({"ok": True, "dati": risposta}))

    page.route("**/api/catering/menu/variazioni?pagina=*", risposta_pagina)
    page.locator('[data-vista="menu"]').click()
    page.wait_for_function("""() =>
        document.querySelectorAll('#menu-variazioni-lista .catering-variazione').length === 10
    """, timeout=12000)
    assert "23/11/2030" in page.locator('#menu-variazioni-lista').inner_text()
    assert page.locator('#menu-variazioni-pagine').is_visible()
    assert page.locator('#menu-variazioni-indicatore').inner_text() == "Pagina 1 di 3"
    assert page.locator('#menu-variazioni-precedenti').is_disabled()
    assert page.locator('#menu-variazioni-successive').is_enabled()
    page.locator('#menu-variazioni-successive').click()
    page.wait_for_function("""() =>
        document.querySelector('#menu-variazioni-indicatore').textContent === 'Pagina 2 di 3'
    """)
    assert page.locator('#menu-variazioni-lista .catering-variazione').count() == 10
    page.locator('#menu-variazioni-successive').click()
    page.wait_for_function("""() =>
        document.querySelector('#menu-variazioni-indicatore').textContent === 'Pagina 3 di 3'
    """)
    assert page.locator('#menu-variazioni-lista .catering-variazione').count() == 3
    assert page.locator('#menu-variazioni-successive').is_disabled()
    controlla_dimensioni(page, f"variazioni paginazione@{larghezza}")
    page.screenshot(path=str(OUTPUT / f"variazioni-paginate-{larghezza}.png"),
                    full_page=True)
    print(f"OK: variazioni@{larghezza}, 23 risultati, pagine da 10, date decrescenti")


def controlla_report_distribuzione(page, larghezza):
    # Una risposta sintetica con 4 classi e 12 varianti alimentari:
    # nessuna tabella a 12 colonne, nessun overflow, somme coerenti.
    sezioni = []
    for indice in range(4):
        tipi = []
        for menu in range(12):
            speciale = int(menu == 0)
            tipi.append({
                "tipologia_id": menu + 1,
                "tipologia_nome": "Dieta scolastica speciale con descrizione lunga " + str(menu + 1),
                "pasti": 2, "pasti_ordinari": 2 - speciale,
                "pasta_in_bianco": speciale,
                "menu_nome": "Settimana autunnale " + str(menu + 1),
                "menu_descrizione": "Primo: pasta\\nSecondo: verdure " + str(menu + 1),
                "menu_origine": "settimanale",
                "richieste_pasta": [{
                    "cognome": "CognomeDiTestMoltoLungo" + str(indice),
                    "nome": "BambinoFittizio", "classe_id": indice + 1,
                    "tipologia_id": menu + 1
                }] if speciale else []
            })
        sezioni.append({
            "classe_id": indice + 1, "classe_nome": "Sezione di prova " + str(indice + 1),
            "pasti": 24, "pasti_ordinari": 23, "pasta_in_bianco": 1, "tipologie": tipi
        })
    tipi_totale = [{
        "tipologia_id": i + 1,
        "tipologia_nome": "Dieta scolastica speciale con descrizione lunga " + str(i + 1),
        "pasti": 8, "pasti_ordinari": 4 if i == 0 else 8,
        "pasta_in_bianco": 4 if i == 0 else 0,
        "menu_nome": "Settimana autunnale " + str(i + 1),
        "menu_descrizione": "Primo: pasta\\nSecondo: verdure " + str(i + 1),
        "menu_origine": "settimanale"
    } for i in range(12)]
    dati = {"data": "2099-10-19", "totale_pasti": 98, "plessi": [{
        "plesso_id": 1, "plesso_nome": "Scuola di collaudo sintetico",
        "totale_pasti": 96, "pasti_ordinari": 92, "totale_pasta_in_bianco": 4,
        "cutoff_orario": "09:15", "definitivo": False,
        "sezioni": sezioni, "tipologie": tipi_totale, "pasta_in_bianco": []
    }, {
        "plesso_id": 2, "plesso_nome": "Seconda scuola fittizia",
        "totale_pasti": 2, "pasti_ordinari": 2, "totale_pasta_in_bianco": 0,
        "cutoff_orario": "09:15", "definitivo": True,
        "sezioni": [{
            "classe_id": 100, "classe_nome": "Classe 1A",
            "pasti": 2, "pasti_ordinari": 2, "pasta_in_bianco": 0,
            "tipologie": [{"tipologia_id": 1, "tipologia_nome": "Standard",
                           "pasti": 2, "pasti_ordinari": 2, "pasta_in_bianco": 0,
                           "richieste_pasta": []}]
        }],
        "tipologie": [{"tipologia_id": 1, "tipologia_nome": "Standard",
                       "pasti": 2, "pasti_ordinari": 2, "pasta_in_bianco": 0}],
        "pasta_in_bianco": []
    }]}
    pagina_report = "**/api/catering/report?data=2099-10-19"
    page.route(pagina_report, lambda route: route.fulfill(
        status=200, content_type="application/json",
        body=json.dumps({"ok": True, "dati": dati})
    ))
    page.locator('[data-vista="report"]').click()
    page.locator('#report-data').fill('2099-10-19')
    page.locator('#btn-report').click()
    page.wait_for_function("""() =>
        document.querySelectorAll('#report .catering-classe').length === 5
    """, timeout=12000)
    controlla_dimensioni(page, f"report esteso@{larghezza}")
    assert page.locator("#report .catering-scuola").count() == 2
    assert page.locator("#report .catering-classe").count() == 5
    assert page.locator("#report .catering-classe__bianco").count() == 4
    assert page.locator("#report .catering-ripartizione__altre").count() == 5
    assert page.locator("#report input, #report select").count() == 0
    assert page.locator("#report .catering-classe table").count() == 0
    assert page.locator("#report .catering-quantita__modello").count() > 0
    assert page.locator("#report").get_by_text("Settimana autunnale 1").count() > 0
    piatti = page.locator('#report .catering-scuola__piatti').first
    assert not piatti.evaluate("(nodo) => nodo.open")
    piatti.locator('summary').click()
    assert piatti.evaluate("(nodo) => nodo.open")
    assert "Secondo: verdure 1" in piatti.inner_text()
    dettagli = page.locator("#report .catering-classe__riga").first
    dettagli.wait_for()
    assert page.locator("#report .catering-classe__riga").first.is_visible()
    altri = page.locator("#report .catering-classe .catering-ripartizione__altre").first
    assert not altri.evaluate("(nodo) => nodo.open")
    altri.locator("summary").click()
    assert altri.evaluate("(nodo) => nodo.open")
    assert "Dieta scolastica speciale con descrizione lunga 12" in altri.inner_text()
    assert "98" in page.locator("#report-riepilogo").inner_text()
    assert page.locator("#report-plesso, #report-classe-cerca").count() == 0
    card_1 = page.locator("#report .catering-scuola").nth(0).bounding_box()
    card_2 = page.locator("#report .catering-scuola").nth(1).bounding_box()
    assert card_1 and card_2
    if larghezza > 900:
        assert abs(card_1["y"] - card_2["y"]) < 10, "Le scuole devono essere affiancate sul desktop"
    else:
        assert card_2["y"] > card_1["y"], "Le scuole devono essere in colonna su mobile/tablet"
    controlla_dimensioni(page, f"report 12 tipologie@{larghezza}")
    page.screenshot(path=str(OUTPUT / f"distribuzione-12-menu-{larghezza}.png"),
                    full_page=True)
    print(f"OK: report compatto@{larghezza}, 2 scuole, 4 classi con 12 tipi, pasta in bianco visibile, dettagli nativi")

def collaudo_completo_famiglia_scuola_cucina(browser):
    """Un vero flusso fra tre utenti, solo sul DB fittizio di staging.

    Mario Testuno (Sezione A): chiede pasta in bianco, la cucina la vede,
    comunica assenza, la cucina aggiorna i conteggi e la famiglia ripristina
    la presenza. Non vengono acquistati o consumati buoni.
    """
    from datetime import date, timedelta
    oggi = date.today()
    # Una data futura di lunedi' e' attiva per l'infanzia.
    lunedi = oggi + timedelta(days=(7 - oggi.weekday()) % 7 or 7)
    giorno = max(lunedi, date(2026, 10, 19)).isoformat()
    mese = giorno[:7]
    contesti = []

    def apri(ruolo, username):
        ctx = browser.new_context(viewport={"width": 375, "height": 850},
                                  locale="it-IT", device_scale_factor=1)
        contesti.append(ctx)
        p = ctx.new_page()
        p.goto(BASE + "/login", wait_until="domcontentloaded")
        p.locator('[data-testid="login-username-input"]').fill(username)
        p.locator('[data-testid="login-password-input"]').fill(PASSWORD)
        p.locator('[data-testid="login-submit-button"]').click()
        p.wait_for_url(BASE + "/" + ruolo, timeout=15000)
        p.locator("main h1:visible").first.wait_for()
        return p

    def aggiorna_cucina(pagina):
        pagina.locator('#report-data').fill(giorno)
        pagina.locator('#btn-report').click()
        pagina.wait_for_function("""() => {
            const stato = document.querySelector('#report-stato');
            return stato && stato.textContent.includes('Pasti per il') &&
                document.querySelectorAll('#report .catering-classe').length > 0;
        }""", timeout=15000)

    try:
        famiglia = apri("genitore", "genitore.staging")
        famiglia.locator('[data-vista="calendario"]').click()
        famiglia.locator('#cal-mese').fill(mese)
        famiglia.locator('#cal-figlio').select_option(label=next(
            x for x in famiglia.locator('#cal-figlio option').all_text_contents()
            if 'Testuno Mario' in x
        ))
        giorno_cal = famiglia.locator('#calendario [data-data="' + giorno + '"]')
        giorno_cal.wait_for(state="visible")
        assert 'Pasto previsto' in giorno_cal.get_attribute('aria-label'), (
            'Nel calendario della famiglia il bambino non risulta presente')
        giorno_cal.click()
        dettaglio = famiglia.locator('#calendario-dettaglio')
        richiedi = dettaglio.locator('[data-cal-pasta="1"]')
        richiedi.wait_for(state="visible")
        richiedi.click()
        dettaglio.locator('[data-cal-pasta="0"]').wait_for(state="visible")
        assert 'Richiesta' in dettaglio.inner_text()
        controlla_dimensioni(famiglia, "Genitore: pasta in bianco richiesta")

        scuola = apri("scuola", "scuola.infanzia.staging")
        scuola.locator('#classe').select_option(label=next(
            x for x in scuola.locator('#classe option').all_text_contents() if 'Sezione A' in x
        ))
        scuola.locator('#data').fill(giorno)
        scuola.locator('#btn-carica').click()
        scuola.wait_for_function("""() =>
            document.querySelector('#scuola-stato')?.textContent.includes('Stai guardando i dati')
            && document.querySelectorAll('#presenze-corpo tr[data-testid]').length === 2
        """, timeout=15000)
        assert 'Testuno Mario' in scuola.locator('#presenze-corpo').inner_text()
        assert '2' in scuola.locator('#scuola-riepilogo').inner_text()
        controlla_dimensioni(scuola, "Scuola: due pasti previsti")

        cucina = apri("catering", "catering.staging")
        aggiorna_cucina(cucina)
        cucina.wait_for_function("""() => {
            const el = document.querySelector('#report');
            return el && el.textContent.includes('Testuno Mario');
        }""", timeout=15000)
        assert '1 pasta in bianco' in cucina.locator('#report').inner_text()
        assert cucina.locator('[data-testid="report-plesso-1"] .saldo').inner_text() == '4', (
            'Il totale dell’intero plesso deve includere la pasta in bianco una sola volta'
        )
        controlla_dimensioni(cucina, "Catering: pasta in bianco visibile")

        # Se il genitore annulla il pasto, la pasta in bianco viene eliminata
        # nello stesso giorno e la cucina ricalcola i pasti senza duplicazioni.
        dettaglio.locator('[data-cal-stato="assente"]').click()
        dettaglio.locator('[data-cal-stato="presente"]').wait_for(state="visible")
        assert 'Non richiesta' in dettaglio.inner_text()
        cucina.locator('#btn-report').click()
        cucina.wait_for_function("""() => {
            const stato = document.querySelector('#report-stato')?.textContent || '';
            const report = document.querySelector('#report')?.textContent || '';
            const totale = document.querySelector('[data-testid="report-plesso-1"] .saldo');
            return stato.includes('Pasti per il') && !report.includes('Testuno Mario')
                && !report.includes('1 pasta in bianco') && totale?.textContent.trim() === '3';
        }""", timeout=15000)
        scuola.locator('#btn-carica').click()
        scuola.wait_for_function("""() => {
            const riga = [...document.querySelectorAll('#presenze-corpo tr')]
                .find(n => n.textContent.includes('Testuno'));
            return riga && riga.textContent.includes('Pasto non previsto');
        }""", timeout=15000)
        # Ripristino della presenza; la pasta in bianco NON deve ricomparire.
        dettaglio.locator('[data-cal-stato="presente"]').click()
        dettaglio.locator('[data-cal-stato="assente"]').wait_for(state="visible")
        assert 'Non richiesta' in dettaglio.inner_text()
        cucina.locator('#btn-report').click()
        cucina.wait_for_function("""() => {
            const totale = document.querySelector('[data-testid="report-plesso-1"] .saldo');
            return totale && totale.textContent.trim() === '4';
        }""", timeout=15000)
        assert 'Testuno Mario' not in cucina.locator('#report').inner_text()
        assert '1 pasta in bianco' not in cucina.locator('#report').inner_text()
        controlla_dimensioni(cucina, "Catering: pasti ripristinati")
        print("OK: browser mobile Genitore → Scuola → Catering; 4→3→4 pasti e pasta in bianco mai conteggiata due volte.")
    finally:
        for ctx in contesti:
            ctx.close()


with sync_playwright() as engine:
    browser = engine.chromium.launch(headless=True, args=["--no-sandbox"])
    try:
        for larghezza in DIMENSIONI:
            for ruolo, username in RUOLI.items():
                ctx = browser.new_context(viewport={"width": larghezza, "height": 900},
                                          device_scale_factor=1, locale="it-IT")
                pagina = ctx.new_page()
                errori = []
                pagina.on("pageerror", lambda errore: errori.append(str(errore)))
                descrizione = f"{ruolo}@{larghezza}"
                try:
                    pagina.goto(BASE + "/login", wait_until="domcontentloaded")
                    pagina.locator('[data-testid="login-username-input"]').fill(username)
                    pagina.locator('[data-testid="login-password-input"]').fill(PASSWORD)
                    pagina.locator('[data-testid="login-submit-button"]').click()
                    pagina.wait_for_url(BASE + "/" + ruolo, timeout=15000)
                    pagina.locator("main h1:visible").first.wait_for(timeout=15000)
                    pagina.wait_for_timeout(250)
                    controlla_etichette(pagina, descrizione + " schermata iniziale")
                    controlla_dimensioni(pagina, descrizione + " schermata iniziale")
                    pagina.screenshot(path=str(OUTPUT / f"{ruolo}-{larghezza}.png"),
                                     full_page=True)
                    schede = pagina.locator("nav.schede [data-vista]")
                    for vista in schede.evaluate_all(
                        "(nodi) => nodi.map(n => n.getAttribute('data-vista'))"
                    ):
                        pagina.locator(f'nav.schede [data-vista="{vista}"]').click()
                        pagina.locator("#vista-" + vista).wait_for(state="visible")
                        pagina.wait_for_timeout(100)
                        controlla_etichette(pagina, descrizione + "/" + vista)
                        controlla_dimensioni(pagina, descrizione + "/" + vista)
                    if ruolo in ("genitore", "operatore", "admin"):
                        controlla_profili_riordinati(pagina, ruolo, larghezza)
                    if ruolo == "operatore" and larghezza == 375:
                        controlla_modale_mobile(pagina)
                    if ruolo == "operatore" and larghezza == 1366:
                        controlla_modali_operatore(pagina)
                    if ruolo == "catering":
                        controlla_report_distribuzione(pagina, larghezza)
                        controlla_pagine_variazioni(pagina, larghezza)
                        if larghezza == 1366:
                            controlla_foto(pagina)
                            pagina.screenshot(path=str(OUTPUT / "catering-foto-1366.png"),
                                             full_page=True)
                    assert not errori, f"Errori JavaScript {descrizione}: {errori}"
                    print(f"OK: {descrizione}, accesso, schede, overflow assente, errori JS assenti")
                except Exception:
                    pagina.screenshot(path=str(OUTPUT / f"ERRORE-{ruolo}-{larghezza}.png"),
                                     full_page=True)
                    raise
                finally:
                    ctx.close()
        # Percorso trasversale a tre profili su dati di prova, senza contabilità.
        collaudo_completo_famiglia_scuola_cucina(browser)
    finally:
        browser.close()
print("OK: collaudo browser completato, inclusa prova funzionale incrociata fra ruoli.")
