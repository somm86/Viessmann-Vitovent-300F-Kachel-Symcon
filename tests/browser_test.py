"""HTML-SDK bridge is simulated; this is not a running Symcon frontend."""
import copy
import json
import os
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
html = (ROOT / 'tests/output/tile.html').read_text()
snapshot = json.loads((ROOT / 'tests/output/snapshot.json').read_text())
checks = 0


def check(condition, message):
    global checks
    assert condition, message
    checks += 1


with sync_playwright() as p:
    options = {'headless': True}
    if os.environ.get('CHROMIUM_PATH'):
        options['executable_path'] = os.environ['CHROMIUM_PATH']
    browser = p.chromium.launch(**options)
    page = browser.new_page(viewport={'width': 1180, 'height': 850}, device_scale_factor=1)
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.route('**/*', lambda route: route.abort())
    page.goto("about:blank")
    page.set_content(html)
    check(page.locator('#modeSelect').is_disabled(), 'outside SDK: read only')
    page.evaluate("() => { window.calls = []; window.requestAction = (ident, value) => { calls.push({ident, value}); return Promise.resolve(); }; }")
    page.wait_for_function("!document.getElementById('modeSelect').disabled")
    check(page.locator('#modeSelect').is_enabled(), 'late SDK bridge activates control')
    page.evaluate('handleMessage', json.dumps(snapshot))
    check(page.locator('#modeSelect').is_enabled(), 'SDK with real profile permits selection')
    check(page.locator('#modeSelect option').count() == 2, 'only profile associations shown')
    check('h' in page.locator('#filter').inner_text(), 'profile unit retained')
    page.locator('#modeSelect').select_option('9.5')
    check(page.evaluate('calls') == [{'ident': 'ChangeMode', 'value': 9.5}], 'typed SDK request')
    check(page.locator('#modeSelect').is_disabled(), 'pending disables duplicate requests')
    check(page.locator('#modeLabel').inner_text() == snapshot['values']['mode']['formatted'], 'no optimistic mode label')
    confirmed = copy.deepcopy(snapshot)
    confirmed['values']['mode'].update(value=9.5, formatted='Testmodus B')
    confirmed['values']['outside']['value'] = -3.2
    page.evaluate('handleMessage', json.dumps(confirmed))
    check(page.locator('#modeSelect').is_enabled(), 'source update confirms request')
    check(page.locator('#modeLabel').inner_text() == 'Testmodus B', 'confirmed label shown')
    check(page.locator('#outside').inner_text() == '-3,2', 'JSON live value applied')
    page.evaluate("window.savedOption = document.querySelector('#modeSelect option'); void 0")
    sensor_update = copy.deepcopy(confirmed)
    sensor_update['values']['outside']['value'] = -4.0
    page.evaluate('handleMessage', json.dumps(sensor_update))
    check(page.evaluate("savedOption === document.querySelector('#modeSelect option')"), 'sensor update keeps selection DOM intact')
    unknown = copy.deepcopy(confirmed)
    unknown['values']['mode'].update(value=99, formatted='Unbekannter Quellwert')
    page.evaluate('handleMessage', json.dumps(unknown))
    check(page.locator('#modeSelect').input_value() == '', 'unknown current value selects no arbitrary mode')
    hostile = copy.deepcopy(confirmed)
    hostile['mode']['choices'][0]['label'] = '<img src=x onerror=alert(1)>'
    page.evaluate('handleMessage', json.dumps(hostile))
    check(page.locator('img').count() == 0, 'labels are plain text')
    page.evaluate("() => { window.requestAction = () => Promise.reject(new Error('test')); }")
    page.locator('#modeSelect').select_option('2.5')
    page.wait_for_function("document.getElementById('feedback').textContent.includes('fehlgeschlagen')")
    check(page.locator('#modeSelect').is_enabled(), 'SDK rejection releases control')
    # Reload resets action state; fake clock checks no-confirmation handling without sleeping.
    page.goto("about:blank")
    page.set_content(html)
    page.clock.install()
    page.evaluate('() => { window.requestAction = () => Promise.resolve(); }')
    page.evaluate('handleMessage', json.dumps(snapshot))
    page.locator('#modeSelect').select_option('9.5')
    page.clock.fast_forward(15001)
    check('Keine Bestätigung' in page.locator('#feedback').inner_text(), 'unconfirmed request times out honestly')
    readonly = copy.deepcopy(snapshot)
    readonly['mode'].update(writable=False, reason='Aktion deaktiviert')
    page.evaluate('handleMessage', json.dumps(readonly))
    check(page.locator('#modeSelect').is_disabled(), 'action removed live')
    missing = copy.deepcopy(readonly)
    missing['missing'] = ['Außenluft']
    missing['values']['outside']['value'] = None
    page.evaluate('handleMessage', json.dumps(missing))
    check(page.locator('#outside').inner_text() == '—', 'missing is not zero')
    check(page.locator('#status').inner_text() == 'Daten unvollständig', 'partial data shown')
    page.evaluate('handleMessage', 'bad json')
    check('nicht gelesen' in page.locator('#feedback').inner_text(), 'malformed message handled')
    # Explicitly synthetic presentation data for the checked-in screenshot.
    demo = copy.deepcopy(snapshot)
    for key, value in {'outside': 6.4, 'supply': 20.1, 'extract': 22.8, 'exhaust': 9.2}.items():
        demo['values'][key]['value'] = value
    demo['values']['flow'].update(value=168, formatted='168 m³/h', hasUnit=True)
    demo['values']['filter'].update(value=1420, formatted='1.420 h', hasUnit=True)
    page.goto("about:blank")
    page.set_content(html)
    page.evaluate('() => { window.requestAction = () => Promise.resolve(); }')
    page.evaluate('handleMessage', json.dumps(demo))
    for width in [1180, 700, 360, 320]:
        page.set_viewport_size({'width': width, 'height': 950})
        check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), f'no overflow at {width}px')
        for selector in ['#outside', '#supply', '#extract', '#exhaust', '#modeSelect']:
            check(page.locator(selector).is_visible(), f'{selector} visible at {width}px')
        if width in [1180, 360]:
            page.screenshot(path=str(ROOT / f'tests/output/dashboard-{width}.png'), full_page=True, animations='disabled')
    check(errors == [], f'no browser exceptions: {errors}')
    browser.close()
print(f'PASS: {checks} browser assertions (simulated HTML-SDK bridge)')
