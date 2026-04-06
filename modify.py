from pathlib import Path
path = Path('assets/js/app.js')
text = path.read_text(encoding='utf-8')
old_lines = ['    const earliestStartMs = Math.min(...operationLines.map((op) =;', '    const maxDate = Math.max(...operationLines.map((op) =;', '    const selectionState = ensurePerformanceTimelineSelectionState();', '    const hasManualSelection = Boolean(selectionState.dateKey);', '    const selectionDateKey = selectionState.dateKey;']
