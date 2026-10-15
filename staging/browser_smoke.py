#!/usr/bin/env python3
"""Collaudo browser sullo staging sintetico: nessuna connessione alla produzione.

Esecuzione sul runner:
  python staging/browser_smoke.py
Richiede: playwright e Chromium. Base consentita solo localhost.
"""
import base64
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
                    if ruolo == "catering" and larghezza == 1366:
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
