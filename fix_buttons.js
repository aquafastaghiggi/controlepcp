const fs = require('fs');
const path = 'assets/js/app.js';
let text = fs.readFileSync(path, 'utf8');
const buttonMarker = '        const buttonsHtml = entries.map';
const listenerMarker = '        programacaoImportSheets.querySelectorAll';
const buttonStart = text.indexOf(buttonMarker);
if (buttonStart === -1) throw new Error('buttons block not found');
const buttonEnd = text.indexOf(listenerMarker, buttonStart);
if (buttonEnd === -1) throw new Error('listener block not found');
