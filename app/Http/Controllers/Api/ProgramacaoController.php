<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CalcularSequenciaAction;
use App\Actions\CriarProgramacaoAction;
use App\Http\Controllers\Controller;
use App\Models\Programacao;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API REST para gerenciamento de programações de produção.
 * Todas as respostas são JSON. Erros de validação retornam 422.
 */
class ProgramacaoController extends Controller
{
    public function __construct(
        private readonly CriarProgramacaoAction $criarProgramacao,
        private readonly CalcularSequenciaAction $calcularSequencia,
    ) {}

    /** Lista programações com filtros básicos */
    public function index(Request $request): JsonResponse
    {
        $query = Programacao::with('linha')
            ->when($request->linha_id, fn ($q) => $q->where('linha_id', $request->linha_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20);

        return response()->json($query);
    }

    /** Retorna uma programação com seus itens e resultados calculados */
    public function show(Programacao $programacao): JsonResponse
    {
        return response()->json(
            $programacao->load(['linha', 'itens', 'resultados'])
        );
    }

    /** Cria uma nova programação em status rascunho */
    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'linha_id'              => 'required|exists:linhas,id',
            'numero_op'             => 'nullable|string|unique:programacoes,numero_op',
            'descricao'             => 'nullable|string|max:255',
            'data_inicio_planejada' => 'required|date',
            'eficiencia'            => 'nullable|numeric|min:1|max:200',
            'origem'                => 'nullable|in:manual,excel,api_erp',
            'itens'                 => 'required|array|min:1',
            'itens.*.sku'           => 'required|string|exists:produtos,sku',
            'itens.*.quantidade'    => 'required|numeric|min:0.01',
            'itens.*.sequencia'     => 'required|integer|min:1',
        ]);

        $programacao = $this->criarProgramacao->executar($dados);

        return response()->json($programacao, 201);
    }

    /** Executa o cálculo de sequenciamento para uma programação */
    public function calcular(Request $request, Programacao $programacao): JsonResponse
    {
        $dados = $request->validate([
            'data_consulta' => 'nullable|date',
        ]);

        $dataConsulta = isset($dados['data_consulta'])
            ? new DateTimeImmutable($dados['data_consulta'])
            : null;

        $programacao = $this->calcularSequencia->executar($programacao, $dataConsulta);

        return response()->json($programacao->load('resultados'));
    }
}
