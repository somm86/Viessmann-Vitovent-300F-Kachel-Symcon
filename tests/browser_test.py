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
        page.wait_for_function("() => { const box = document.querySelector('.dashboard').getBoundingClientRect(); return box.bottom <= innerHeight + 1 && box.left >= -1 && box.right <= innerWidth + 1 && box.width >= innerWidth - 2; }")
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
    page.wait_for_function("() => { const box = document.querySelector('.dashboard').getBoundingClientRect(); return box.bottom <= innerHeight + 1 && box.left >= -1 && box.right <= innerWidth + 1 && box.width >= innerWidth - 2; }")
    page.evaluate('() => { window.calls = []; window.requestAction = (ident, value) => { calls.push({ident, value}); }; }')
    page.locator('#modeButtons button').nth(1).click()
    check(page.evaluate('calls[0].value') == -702, 'scaled mode button still dispatches correct source value')
    confirmed_small = copy.deepcopy(demo)
    confirmed_small['values']['mode'].update(value=-702, formatted='Reduziert')
    page.evaluate('handleMessage', json.dumps(confirmed_small))
    page.locator('#diagnosis').click()
    page.wait_for_function("() => { const box = document.querySelector('.dashboard').getBoundingClientRect(); return box.bottom <= innerHeight + 1 && box.left >= -1 && box.right <= innerWidth + 1 && box.width >= innerWidth - 2; }")
    box = page.locator('.dashboard').bounding_box()
    check(box['y'] + box['height'] <= 351, 'expanding diagnosis refits to available height')
    # Temperature dialogs use the same authenticated SDK bridge, with read-only archive queries.
    page.set_viewport_size({'width': 1000, 'height': 700})
    page.evaluate('handleMessage', json.dumps(demo))
    page.evaluate('() => { window.calls = []; window.requestAction = (ident, value) => { calls.push({ident, value}); }; }')
    page.locator('.air.outside').click()
    check(page.locator('#historyDialog').is_visible(), 'temperature click opens graph dialog')
    request = json.loads(page.evaluate('calls.at(-1).value'))
    check(page.evaluate('calls.at(-1).ident') == 'LoadHistory' and request['key'] == 'outside', 'graph uses configured temperature key')
    check(request['end'] - request['start'] == 86400, 'default window is 24 hours')
    check(page.locator('#historyPrevious').is_disabled(), 'duplicate navigation disabled while loading')

    def reply_history(empty=False, error='', request_override=None):
        request = request_override or json.loads(page.evaluate('calls.at(-1).value'))
        step = 86400 if request['end'] - request['start'] > 31 * 86400 else 3600
        data = {'requestId': request['requestId'], 'key': request['key'], 'variableID': demo['values'][request['key']]['id'],
                'start': request['start'], 'end': request['end'], 'step': step, 'error': error,
                'aggregation': 'Tägliche Mittelwerte' if step == 86400 else 'Stündliche Mittelwerte', 'points': [] if empty else [
                    {'time': request['start'] + step, 'value': 15.2},
                    {'time': request['start'] + step * 2, 'value': 16.4}]}
        page.evaluate('handleMessage', json.dumps({'history': data}))

    reply_history()
    check(page.locator('#historyChart').is_visible(), 'actual archive response draws chart')
    check(page.locator('#historyChart circle').count() == 2, 'only returned archive points plotted')
    check(page.locator('#historyNext').is_disabled(), 'cannot navigate into future')
    initial_end = request['end']
    page.locator('#historyPrevious').click()
    previous = json.loads(page.evaluate('calls.at(-1).value'))
    check(previous['end'] == initial_end - 86400, 'back jumps exactly one selected interval')
    reply_history()
    page.locator('#historyNext').click()
    following = json.loads(page.evaluate('calls.at(-1).value'))
    check(following['end'] == initial_end, 'forward restores next interval')
    reply_history()
    page.locator('#historyRange').select_option('604800')
    week = json.loads(page.evaluate('calls.at(-1).value'))
    check(week['end'] - week['start'] == 7 * 86400, 'time range selection changes requested window')
    reply_history()
    page.locator('#historyPrevious').click()
    check(json.loads(page.evaluate('calls.at(-1).value'))['end'] == initial_end - 7 * 86400, 'navigation respects newly selected interval')
    reply_history()
    page.locator('#historyNow').click()
    now_request = json.loads(page.evaluate('calls.at(-1).value'))
    check(now_request['end'] >= initial_end, 'Now returns to current interval')
    reply_history()
    page.screenshot(path=str(ROOT / 'tests/output/temperature-history.png'), animations='disabled')
    page.locator('#historyRange').select_option('2592000')
    month = json.loads(page.evaluate('calls.at(-1).value'))
    check(month['end'] - month['start'] == 30 * 86400, 'month view requests bounded 30 days')
    reply_history()
    page.locator('#historyRange').select_option('31536000')
    year = json.loads(page.evaluate('calls.at(-1).value'))
    check(year['end'] - year['start'] == 365 * 86400, 'year view requests bounded 365 days')
    reply_history(empty=True)
    check('Keine archivierten Werte' in page.locator('#historyStatus').inner_text(), 'empty history is explained')
    check(page.locator('#historyChart').is_hidden(), 'empty history has no fabricated curve')
    page.locator('#historyClose').click()
    check(page.locator('#historyDialog').is_hidden(), 'close button dismisses graph')
    for key in ['supply', 'extract', 'exhaust']:
        card = page.locator('.air.' + key)
        card.focus()
        card.press('Enter')
        check(json.loads(page.evaluate('calls.at(-1).value'))['key'] == key, f'{key} opens correct source by keyboard')
        reply_history(error='Fixture: keine Archivprotokollierung aktiv')
        check('Archivprotokollierung' in page.locator('#historyStatus').inner_text(), 'archive error visible without changing mode data')
        page.keyboard.press('Escape')
    check(page.locator('#modeLabel').inner_text() == demo['values']['mode']['formatted'], 'history responses preserve live mode data')
    # Stale graph responses must not replace a more recent request.
    page.locator('.air.outside').click()
    old_request = json.loads(page.evaluate('calls.at(-1).value'))
    page.locator('#historyClose').click()
    page.locator('.air.supply').click()
    reply_history(request_override=old_request)
    check('Lade Archivwerte' in page.locator('#historyStatus').inner_text(), 'late old response ignored')
    reply_history()
    page.locator('#historyClose').click()
    page.locator('.air.extract').click()
    page.clock.fast_forward(15001)
    check('Keine Antwort' in page.locator('#historyStatus').inner_text(), 'archive timeout is recoverable')
    check(page.locator('#historyPrevious').is_enabled(), 'navigation unlocked after archive timeout')
    page.locator('#historyClose').click()
    page.set_viewport_size({'width': 320, 'height': 360})
    page.locator('.air.outside').click()
    reply_history()
    dialog_box = page.locator('#historyDialog').bounding_box()
    check(dialog_box['x'] >= 0 and dialog_box['x'] + dialog_box['width'] <= 321, 'graph dialog fits narrow tile')
    check(dialog_box['y'] >= 0 and dialog_box['y'] + dialog_box['height'] <= 361, 'graph dialog fits available height')
    check(page.locator('#historyChart').evaluate('el => el.viewBox.baseVal.width <= el.clientWidth + 1'), 'chart labels use actual narrow dialog width')
    page.locator('#historyClose').click()
    check(errors == [], f'no browser exceptions: {errors}')
    browser.close()
print(f'PASS: {checks} browser assertions (simulated HTML-SDK bridge)')
