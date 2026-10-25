"""UI and official void-return HTML-SDK bridge simulation; no real Symcon frontend."""
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
    page = browser.new_page(viewport={'width': 1536, 'height': 1024}, device_scale_factor=1)
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.route('**/*', lambda route: route.abort())
    page.goto('about:blank')
    page.set_content(html)
    buttons = page.locator('#modeButtons button')
    check(buttons.first.is_disabled(), 'outside SDK: read only')
    # The official SDK is void-return, not Promise-return.
    page.evaluate("() => { window.calls = []; window.requestAction = (ident, value) => { calls.push({ident, value}); }; }")
    page.wait_for_function("!document.querySelector('#modeButtons button').disabled")
    check(buttons.first.is_enabled(), 'late SDK bridge activates control')
    check(buttons.count() == 2, 'only actual profile choices become mode tiles')
    check('h' in page.locator('#filter').inner_text(), 'profile unit retained')
    buttons.nth(1).click()
    check(page.evaluate('calls') == [{'ident': 'ChangeMode', 'value': 9.5}], 'typed SDK request')
    check(buttons.first.is_disabled(), 'pending disables duplicate requests')
    check(page.locator('#modeLabel').inner_text() == snapshot['values']['mode']['formatted'], 'no optimistic mode label')
    check(buttons.first.get_attribute('aria-pressed') == 'true', 'pending does not highlight unconfirmed value')
    confirmed = copy.deepcopy(snapshot)
    confirmed['values']['mode'].update(value=9.5, formatted='Testmodus B')
    confirmed['values']['outside']['value'] = -3.2
    page.evaluate('handleMessage', json.dumps(confirmed))
    check(buttons.first.is_enabled(), 'source update confirms request')
    check(page.locator('#modeLabel').inner_text() == 'Testmodus B', 'confirmed label shown')
    check(buttons.nth(1).get_attribute('aria-pressed') == 'true', 'confirmed tile highlighted')
    check(page.locator('#outside').inner_text() == '-3,2', 'JSON live value applied')
    page.evaluate("window.savedButton = document.querySelector('#modeButtons button'); void 0")
    sensor_update = copy.deepcopy(confirmed)
    sensor_update['values']['outside']['value'] = -4.0
    page.evaluate('handleMessage', json.dumps(sensor_update))
    check(page.evaluate("savedButton === document.querySelector('#modeButtons button')"), 'sensor update preserves focused mode button')
    unknown = copy.deepcopy(confirmed)
    unknown['values']['mode'].update(value=99, formatted='Unbekannter Quellwert')
    page.evaluate('handleMessage', json.dumps(unknown))
    check(page.locator('#modeButtons button[aria-pressed="true"]').count() == 0, 'unknown source mode highlights no arbitrary choice')
    hostile = copy.deepcopy(confirmed)
    hostile['mode']['choices'][0]['label'] = '<img src=x onerror=alert(1)>'
    page.evaluate('handleMessage', json.dumps(hostile))
    check(page.locator('img').count() == 0, 'labels are plain text')
    # PHP cannot return an exception through the documented void function; it publishes an error message.
    buttons.first.click()
    php_error = copy.deepcopy(confirmed)
    php_error['actionError'] = 'Fixture: Geräteaktion fehlgeschlagen'
    page.evaluate('handleMessage', json.dumps(php_error))
    check('Geräteaktion fehlgeschlagen' in page.locator('#feedback').inner_text(), 'PHP error reaches visible feedback')
    check(buttons.first.is_enabled(), 'server error releases pending control')
    page.evaluate('handleMessage', json.dumps(confirmed))
    # Optional Promise-return bridge variants still propagate a rejection.
    page.evaluate("() => { window.requestAction = () => Promise.reject(new Error('test')); }")
    buttons.first.click()
    page.wait_for_function("document.getElementById('feedback').textContent.includes('Schalten fehlgeschlagen')")
    check(buttons.first.is_enabled(), 'optional Promise rejection releases control')
    page.goto('about:blank')
    page.set_content(html)
    page.clock.install()
    page.evaluate('() => { window.requestAction = () => {}; }')
    page.evaluate('handleMessage', json.dumps(snapshot))
    page.locator('#modeButtons button').nth(1).click()
    page.clock.fast_forward(15001)
    check('Keine Bestätigung' in page.locator('#feedback').inner_text(), 'unconfirmed request times out honestly')
    readonly = copy.deepcopy(snapshot)
    readonly['mode'].update(writable=False, reason='Aktion deaktiviert')
    page.evaluate('handleMessage', json.dumps(readonly))
    check(page.locator('#modeButtons button').first.is_disabled(), 'action removed live')
    no_choices = copy.deepcopy(readonly)
    no_choices['mode'].update(choices=[], reason='Keine Auswahlwerte hinterlegt')
    page.evaluate('handleMessage', json.dumps(no_choices))
    check(page.locator('#modeButtons button').count() == 0, 'missing choices do not invent four preset modes')
    check('Keine Betriebsarten' in page.locator('#modeButtons').inner_text(), 'empty configuration has clear state')
    page.locator('#diagnosis').click()
    check(page.locator('#diagnosticOutput').is_visible(), 'actual configuration diagnosis is available')
    check(json.loads(page.locator('#diagnosticOutput').inner_text())['choices'] == [], 'diagnosis contains actual empty choices')
    missing = copy.deepcopy(readonly)
    missing['missing'] = ['Außenluft']
    missing['values']['outside']['value'] = None
    page.evaluate('handleMessage', json.dumps(missing))
    check(page.locator('#outside').inner_text() == '—', 'missing is not zero')
    check(page.locator('#status').inner_text() == 'Daten unvollständig', 'partial data shown')
    page.evaluate('handleMessage', 'bad json')
    check('nicht gelesen' in page.locator('#feedback').inner_text(), 'malformed message handled')
    # Actual enumeration data can include strings and booleans. No numeric coercion.
    page.evaluate('() => { window.calls = []; window.requestAction = (ident, value) => { calls.push({ident, value}); }; }')
    string_mode = copy.deepcopy(snapshot)
    string_mode['mode']['choices'] = [{'value': 'fixture-a', 'label': 'Fixture A'}, {'value': 'fixture-b', 'label': 'Fixture B'}]
    string_mode['values']['mode']['value'] = 'fixture-a'
    page.evaluate('handleMessage', json.dumps(string_mode))
    page.locator('#modeButtons button').nth(1).click()
    check(page.evaluate('calls[0].value') == 'fixture-b', 'string enumeration preserves type in SDK request')
    boolean_mode = copy.deepcopy(snapshot)
    boolean_mode['mode']['choices'] = [{'value': False, 'label': 'Fixture false'}, {'value': True, 'label': 'Fixture true'}]
    boolean_mode['values']['mode']['value'] = 'fixture-b'  # Finish previous pending request first.
    page.evaluate('handleMessage', json.dumps(boolean_mode))
    boolean_mode['values']['mode']['value'] = False
    page.evaluate('handleMessage', json.dumps(boolean_mode))
    page.locator('#modeButtons button').nth(1).click()
    check(page.evaluate('calls[1].value') is True, 'boolean enumeration preserves type in SDK request')
    # Explicit synthetic screenshot fixture; never installed as production modes or ranges.
    demo = copy.deepcopy(snapshot)
    for key, value in {'outside': 15.6, 'supply': 22.3, 'extract': 22.4, 'exhaust': 16.8}.items():
        demo['values'][key]['value'] = value
    demo['values']['flow'].update(value=85, formatted='85 m³/h', hasUnit=True, range={'min': 0, 'max': 200})
    demo['values']['filter'].update(value=281, formatted='281 Tage', hasUnit=True, range={'min': 0, 'max': 365})
    demo['values']['mode'].update(value=-701, formatted='Grundbetrieb')
    demo['mode']['choices'] = [
        {'value': -701, 'label': 'Grundbetrieb'}, {'value': -702, 'label': 'Reduziert'},
        {'value': -703, 'label': 'Intensiv'}, {'value': -704, 'label': 'Party'}]
    page.goto('about:blank')
    page.set_content(html)
    page.evaluate('() => { window.requestAction = () => {}; }')
    # No source range means no made-up percent gauge.
    page.evaluate('handleMessage', json.dumps(snapshot))
    check(page.locator('#flowTrack').is_hidden(), 'missing source range hides flow bar')
    check(page.locator('#filterTrack').is_hidden(), 'missing source range hides filter bar')
    page.evaluate('handleMessage', json.dumps(demo))
    check(page.locator('#flowBar').evaluate('el => el.style.width') == '42.5%', 'bar uses actual supplied source range')
    symbol_sizes = {}
    for width in [1536, 1180, 800, 700, 360, 320]:
        page.set_viewport_size({'width': width, 'height': 1024})
        check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), f'no overflow at {width}px')
        for selector in ['#outside', '#supply', '#extract', '#exhaust', '#modeButtons']:
            check(page.locator(selector).is_visible(), f'{selector} visible at {width}px')
        if width in [1536, 360]:
            symbol_sizes[width] = {selector: page.locator(selector).first.bounding_box()['width'] for selector in ['.outside > .icon', '.metric-icon', '.mode-button .icon', '.device']}
            page.screenshot(path=str(ROOT / f'tests/output/dashboard-{width}.png'), full_page=True, animations='disabled')
    for selector in symbol_sizes[1536]:
        check(symbol_sizes[1536][selector] > symbol_sizes[360][selector], f'{selector} scales with tile width')
    check(page.locator('.outside > .icon').first.is_visible(), 'air symbol remains visible in small tiles')
    # Regression: changing only the tile height must shrink the entire dashboard.
    height_sizes = {}
    for width, height in [(1180, 1000), (1180, 300), (800, 450), (360, 360), (320, 200), (360, 1400), (1412, 1168), (2200, 1100)]:
        page.set_viewport_size({'width': width, 'height': height})
        page.evaluate('() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))')
        box = page.locator('.dashboard').bounding_box()
        check(box['y'] >= -1 and box['y'] + box['height'] <= height + 1, f'dashboard fits height {width}x{height}')
        check(box['x'] >= -1 and box['x'] + box['width'] <= width + 1, f'dashboard fits width {width}x{height}')
        check(box['width'] >= width - 2, f'dashboard uses full tile width {width}x{height}')
        button_box = page.locator('#modeButtons button').last.bounding_box()
        check(button_box['y'] + button_box['height'] <= height + 1, f'mode control stays inside {width}x{height}')
        if width == 1180:
            height_sizes[height] = page.locator('.metric-icon').first.bounding_box()['height']
        if (width, height) == (1180, 300):
            page.screenshot(path=str(ROOT / 'tests/output/dashboard-flat.png'), animations='disabled')
    check(height_sizes[300] < height_sizes[1000], 'symbols shrink when only available height shrinks')
    page.set_viewport_size({'width': 800, 'height': 350})
    page.evaluate('() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))')
    page.evaluate('() => { window.calls = []; window.requestAction = (ident, value) => { calls.push({ident, value}); }; }')
    page.locator('#modeButtons button').nth(1).click()
    check(page.evaluate('calls[0].value') == -702, 'scaled mode button still dispatches correct source value')
    confirmed_small = copy.deepcopy(demo)
    confirmed_small['values']['mode'].update(value=-702, formatted='Reduziert')
    page.evaluate('handleMessage', json.dumps(confirmed_small))
    page.locator('#diagnosis').click()
    page.evaluate('() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))')
    box = page.locator('.dashboard').bounding_box()
    check(box['y'] + box['height'] <= 351, 'expanding diagnosis refits to available height')
    check(errors == [], f'no browser exceptions: {errors}')
    browser.close()
print(f'PASS: {checks} browser assertions (simulated HTML-SDK bridge)')
