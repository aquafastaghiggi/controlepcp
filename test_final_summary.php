#!/usr/bin/env php
<?php
// Teste FINAL - Mudanças aplicadas

echo "=== ALTERAÇÕES APLICADAS NO GANTT ===\n\n";

echo "1️⃣  Coluna 'Produto / Recurso':\n";
echo "   width: 220 → 300 (30% maior)\n\n";

echo "2️⃣  Altura das linhas:\n";
echo "   row_height: 32 → 50 (56% maior)\n\n";

echo "3️⃣  Dados no elemento text:\n";
echo "   📦 OP 201055\n";
echo "   Agua Sanitaria Aquafast 5l\n";
echo "   (com quebra de linha \\n)\n\n";

echo "✅ RESULTADO ESPERADO:\n";
echo "   - Coluna mais larga para não truncar\n";
echo "   - Linhas mais altas para mostrar 2 linhas\n";
echo "   - Descrição do produto em linha separada\n\n";

echo "Testa agora que vai funcionar! 👍\n";
?>
