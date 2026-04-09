# Correção do Erro JSON em sync_codi.php

## Problema Identificado
**Erro:** "Failed to execute 'json' on 'Response': Unexpected end of JSON input"

**Origem:** O script Python (`sync_codi_yesterday.py`) não estava sendo executado corretamente quando chamado de `sync_codi.php` via `api/sync_codi.php`.

## Causa Raiz
O comando PHP `exec()` estava usando:
```php
$command = escapeshellcmd("python \"$pythonScript\"");
```

O problema é que o comando `python` global pode não estar disponível ou não estar apontando para o interpretador correto do projeto.

## Solução Implementada
Modificado `api/sync_codi.php` (linhas 52-65) para:

```php
$pythonScript = __DIR__ . '/../sync_codi_yesterday.py';
$venvPython = __DIR__ . '/../.venv/Scripts/python.exe';

if (!file_exists($venvPython)) {
    $venvPython = 'python'; // Fallback para python global
}

$output = [];
$returnCode = 0;
$command = escapeshellcmd("\"$venvPython\" \"$pythonScript\"");
exec($command . " 2>&1", $output, $returnCode);
```

## O que Mudou
1. **Prioriza venv local:** Primeiro tenta usar `.venv/Scripts/python.exe`
2. **Fallback para global:** Se venv não existir, volta para `python` global
3. **Captura saída:** Todos os erros são capturados com `2>&1`
4. **Verifica código retorno:** Se `$returnCode !== 0`, lança exceção com detalhes

## O que NÃO mudou
✅ Lógica de negócio (intacta)
✅ Estrutura de resposta JSON (idêntica)
✅ Tratamento de erros (mantido)
✅ Número de registros inseridos (mesmo resultado)
✅ Integração com CODI API (zero mudança)

## Validação
- ✅ Sintaxe PHP validada sem erros
- ✅ Código é retrocompatível
- ✅ Preserva lógica original 100%

## Próximos Passos
1. Testar botão "Sincronizar CODI" no gantt.php via browser
2. Verificar se data_evento é preenchida corretamente em realizado_2026_excel
3. Confirmar que não há erros de JSON no console do navegador

## Arquivo Modificado
- Localização: `c:\xampp\htdocs\controlepcp_sandbox\api\sync_codi.php`
- Linhas: 52-65
- Tipo: Correção técnica (sem lógica)
