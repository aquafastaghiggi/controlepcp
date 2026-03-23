const fs = require('fs');
const path = 'assets/js/app.js';
let text = fs.readFileSync(path, 'utf8');

const insertAfter = "        saveState({ meta: { skip_products: true } });\r\n\r\n        try {";
const replacement = "        saveState({ meta: { skip_products: true } });\r\n\r\n        const valorOp = form.querySelector('[name=\"numero_op\"]')?.value\r\n            || (state.form.items.find((item) => item.op)?.op) || state.form.numero_op || null;\r\n\r\n        try {";
if (!text.includes(insertAfter)) {
    throw new Error('insert anchor not found');
}
text = text.replace(insertAfter, replacement);

const payloadTarget = "                    numero_op: form.querySelector('[name=\"numero_op\"]')?.value || null,\r\n                    production_efficiency:";
const payloadReplacement = "                    numero_op: valorOp,\r\n                    prg_numero_op: valorOp,\r\n                    production_efficiency:";
if (!text.includes(payloadTarget)) {
    throw new Error('payload anchor not found');
}
text = text.replace(payloadTarget, payloadReplacement);

fs.writeFileSync(path, text);
