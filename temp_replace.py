
from pathlib import Path
path = Path('assets/js/app.js')
text = path.read_text(encoding='utf-8')
pipe = chr(124)
or_op = pipe + pipe
