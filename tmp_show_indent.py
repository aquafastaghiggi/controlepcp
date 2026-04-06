from pathlib import Path
lines = Path("assets/js/app.js").read_text(encoding="utf-8").splitlines()
for i in range(5413, 5418):
    print(repr(lines[i]))
