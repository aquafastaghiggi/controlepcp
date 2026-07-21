# Gap de dados do CODI — Turno da Noite (Envase)

## O sintoma

O card "Produção Ontem" (tela `/acompanhar-producao`) mostra um valor abaixo do
que o BI interno aponta. Exemplo real (20/07/2026):

- Card do sistema: **14.592 cx**
- BI interno (com turno da noite): **16.051 cx**
- BI interno (sem turno da noite): **14.048 cx**

O valor do card fica *entre* os dois números do BI, mais perto do "sem noite" —
o que faz parecer que o turno da noite (T4, 23h→03h) não está sendo somado.

## O que NÃO é o problema

A fórmula do card está correta. A janela de cálculo já é
`06:00 de ontem → 03:00 de hoje` (arquivo
`app/Livewire/Dashboard/AcompanharProducao.php`, bloco "Ontem"), que **inclui**
corretamente o horário do T4. Conferido manualmente: a soma bate byte a byte
com o valor exibido (14.592,52 → arredonda pra 14.592).

## O que É o problema

Para as 7 linhas do Envase (`codigo_recurso` 6, 7, 8, 9, 10, 11, 22 — LN01 a
LN07), a tabela `codi_eventos` tem **zero eventos de qualquer tipo** (nem
`PRODUCAO`, nem `PARADA`, nada) entre **00:00 e 06:00**, todos os dias.
Confirmado retroativamente por 7 noites seguidas (14/07 a 21/07/2026), sem
exceção — não foi um dia isolado.

Ao mesmo tempo, **outros recursos** (fora da lista das 7 linhas do Envase —
provavelmente Sopro ou outros equipamentos) **têm** eventos fluindo
normalmente nessa mesma janela, e o log de sincronização
(`storage/logs/laravel.log`) mostra o comando `codi:sincronizar` rodando e
processando eventos normalmente a cada minuto durante a madrugada. Ou seja:
**o sync não está quebrado — o CODI simplesmente não entrega dado nenhum das
linhas do Envase nesse intervalo.**

Hipótese descartada: produção da noite sendo lançada com atraso, só quando o
T1 liga (~07:05). Verificado — os primeiros eventos do dia a partir das 07h
são lotes pequenos e normais, sem nenhum volume anômalo que sugerisse um
"catch-up" da noite represado.

## Onde investigar (fora do nosso sistema)

Esse gap está a montante do ControlePCP — no CODI ou na coleta física/PLC das
linhas do Envase durante a madrugada. Não é algo que se resolve editando a
query do card; é preciso verificar com quem administra a integração
CODI/coleta de dados das linhas por que elas não reportam nada entre 00h-06h.

## Como reproduzir esse diagnóstico

```php
php artisan tinker --execute="
\$recursosLinhas = ['6','7','8','9','10','11','22'];
for (\$i = 0; \$i <= 6; \$i++) {
    \$inicio = \Carbon\Carbon::today()->subDays(\$i)->setHour(0)->setMinute(0)->setSecond(0);
    \$fim = \Carbon\Carbon::today()->subDays(\$i)->setHour(6)->setMinute(0)->setSecond(0);
    \$c = \App\Models\Codi\CodiEvento::whereIn('codigo_recurso',\$recursosLinhas)->where('inicio_evento','>=',\$inicio)->where('inicio_evento','<',\$fim)->count();
    echo \$inicio->format('Y-m-d') . ' 00h-06h: ' . \$c . ' eventos' . PHP_EOL;
}
"
```

Se o resultado voltar a mostrar contagem 0 em várias noites seguidas, o
problema é o mesmo gap — não é regressão nova no código.

## Histórico

| Data | Quem reportou | Observação |
|------|------|------|
| 21/07/2026 | Usuário (comparando com BI interno) | Investigação completa, causa identificada como gap de dados do CODI, não bug de cálculo |
