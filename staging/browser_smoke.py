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

def controlla_foto(page):
    # Il test non salva file: utilizza la bozza locale della foto nel form.
    page.locator('[data-vista="menu"]').click()
    page.locator('#vista-menu').wait_for(state="visible")
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
        "pasta_in_bianco": 4 if i == 0 else 0
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
                        controlla_dimensioni(pagina, descrizione + "/" + vista)
                    if ruolo == "catering":
                        controlla_report_distribuzione(pagina, larghezza)
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
