from pathlib import Path
lines = Path("assets/js/app.js").read_text(encoding="utf-8").splitlines()
for i in range(5412, 5426):
    print(f"{i+1:04d}: {lines[i]}")
