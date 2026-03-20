const fs = require('fs');
const path = 'assets/js/app.js';
let text = fs.readFileSync(path, 'utf8');
const startMarker = '        const buttonsHtml = entries.map((sheet, index) =
const start = text.indexOf(startMarker);
if (start === -1) throw new Error('start marker missing');
const endMarker = '        programacaoImportSheets.innerHTML = ';
const end = text.indexOf(endMarker, start);
if (end === -1) throw new Error('end marker missing');
