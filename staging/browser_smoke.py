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


def controlla_modali_operatore(page):
    """Tutti i moduli sono aperti ma mai inviati: nessuna mutazione DB."""
    page.locator('[data-vista="famiglie"]').click()
    page.locator('#btn-nuovo-genitore').click()
    page.locator('[data-testid="genitore-form"]').wait_for(state="visible")
    controlla_etichette(page, 'Operatore / nuova famiglia')
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
    finally:
        browser.close()
print("OK: collaudo browser completato con 5 ruoli e 3 larghezze, staging sintetico.")
