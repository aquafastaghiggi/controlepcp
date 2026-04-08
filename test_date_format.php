<?php
require 'src/bootstrap.php';

use App\Repository\ProgramacaoRepository;

$repo = new ProgramacaoRepository();
$programacoes = $repo->getAllProgramacoes(3, 0);

echo "=== Teste de Formatação de Datas ===\n\n";

if (!empty($programacoes)) {
    foreach ($programacoes as $prg) {
        $linha = $prg['linha_excel_dominante'] ?: $prg['lin_codigo'] ?: 'S/Linha';
        $inicio = $prg['inicio_base_cronograma'] ? date('d/m/Y H:i', strtotime($prg['inicio_base_cronograma'])) : 'S/data';
        $prog = $prg['programacao_criada_em'] ? date('d/m/Y H:i', strtotime($prg['programacao_criada_em'])) : 'S/data';
        $eff = $prg['prg_eficiencia'] ?? 0;
        
        echo "Linha $linha | Início: $inicio | Prog: $prog | Ef: " . $eff . '%' . "\n";
    }
}
?>
