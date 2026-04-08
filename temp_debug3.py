from pathlib import Path
text = Path('graficosequenciamento.php').read_text(encoding='utf-8')
start = text.index('      <label>Período')
print(repr(text[start:start+600]))
