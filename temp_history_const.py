from pathlib import Path
path = Path('assets/js/app.js')
data = path.read_text(encoding='utf-8')
old = '''    const searchOpInput = document.getElementById('search-op');
    const prgIdInput = document.getElementById('prg_id');'''
if old not in data:
    raise SystemExit('target block not found')
new = '''    const searchOpInput = document.getElementById('search-op');
    const prgIdInput = document.getElementById('prg_id');
    const historyList = document.getElementById('history-list');
    const historyEmpty = document.getElementById('history-empty');
    const historyRefreshButton = document.getElementById('history-refresh');'''
data = data.replace(old, new, 1)
path.write_text(data, encoding='utf-8')
