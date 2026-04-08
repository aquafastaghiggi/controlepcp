const fs = require('fs');

// Ler o gantt.php
const content = fs.readFileSync('c:\\xampp\\htdocs\\controlepcp_sandbox\\gantt.php', 'utf-8');

// Verificar se o template tem os elementos corretos
const checks = [
    {
        name: 'Template contém "descricao_produto"',
        regex: /task\.descricao_produto/,
        found: false
    },
    {
        name: 'Template contém <br>',
        regex: /\\<br\\>/,
        found: false
    },
    {
        name: 'Campo descricao_produto adicionado ao task object',
        regex: /\'descricao_produto\':/,
        found: false
    },
    {
        name: 'JSON.stringify dos tasks está presente',
        regex: /json_encode\(\$tasks\)/,
        found: false
    }
];

checks.forEach(check => {
    check.found = check.regex.test(content);
    console.log(`${check.found ? '✅' : '❌'} ${check.name}`);
});

// Mostrar um trecho do template
console.log('\n=== Trecho do Template ===');
const templateMatch = content.match(/template: function\(task\) \{[\s\S]*?\n            \}/);
if (templateMatch) {
    console.log(templateMatch[0].substring(0, 500) + '...');
}

const allPassed = checks.every(c => c.found);
console.log(`\n${allPassed ? '✅ PRONTO' : '❌ ERROS ENCONTRADOS'}`);
process.exit(allPassed ? 0 : 1);
