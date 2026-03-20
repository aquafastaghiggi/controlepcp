
from pathlib import Path
path = Path('api/calculate.php')
text = path.read_text()
question_marks = '?' * 2
or_operator = '|' * 2
