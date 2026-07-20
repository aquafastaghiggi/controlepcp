<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Linha;
use App\Models\OrdemProducao;
use App\Services\OrdemProducaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class OrdemProducaoServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrdemProducaoService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrdemProducaoService::class);
    }

    // -------------------------------------------------------------------------
    // criar()
    // -------------------------------------------------------------------------

    public function test_criar_salva_ordem_no_banco(): void
    {
        $dados = [
            'sku'               => 'TEST01',
            'descricao_produto' => 'Produto Teste',
            'quantidade'        => 100,
        ];

        $resultado = $this->service->criar($dados);

        $this->assertInstanceOf(OrdemProducao::class, $resultado);
        $this->assertDatabaseHas('ordens_producao', ['sku' => $dados['sku']]);
    }

    public function test_criar_gera_numero_op_automatico_quando_omitido(): void
    {
        $dados = [
            'sku'               => 'TEST02',
            'descricao_produto' => 'Produto Teste',
            'quantidade'        => 50,
        ];

        $ordem = $this->service->criar($dados);

        $this->assertMatchesRegularExpression('/^OP\d{6}$/', $ordem->numero_op);
    }

    public function test_criar_preserva_numero_op_quando_fornecido(): void
    {
        $dados = [
            'sku'               => 'TEST03',
            'descricao_produto' => 'Produto Teste',
            'quantidade'        => 75,
            'numero_op'         => 'OP-TESTE-001',
        ];

        $ordem = $this->service->criar($dados);

        $this->assertSame('OP-TESTE-001', $ordem->numero_op);
    }

    public function test_criar_lanca_validacao_quando_sku_ausente(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->criar([
            'descricao_produto' => 'Teste',
            'quantidade'        => 100,
        ]);
    }

    public function test_criar_lanca_validacao_quando_quantidade_invalida(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->criar([
            'sku'               => 'TEST04',
            'descricao_produto' => 'Produto Teste',
            'quantidade'        => 0,
        ]);
    }

    // -------------------------------------------------------------------------
    // atualizarStatus()
    // -------------------------------------------------------------------------

    public function test_atualizar_status_transicao_valida(): void
    {
        $ordem = OrdemProducao::factory()->create([
            'sku'               => 'TEST05',
            'descricao_produto' => 'Produto Teste',
            'quantidade'        => 100,
            'status'            => 'pendente',
        ]);

        $resultado = $this->service->atualizarStatus($ordem, 'programada');

        $this->assertSame('programada', $resultado->status);
        $this->assertDatabaseHas('ordens_producao', [
            'id'     => $ordem->id,
            'status' => 'programada',
        ]);
    }

    public function test_atualizar_status_transicao_invalida_lanca_excecao(): void
    {
        $ordem = OrdemProducao::factory()->concluida()->create([
            'sku'               => 'TEST06',
            'descricao_produto' => 'Produto Teste',
            'quantidade'        => 100,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->atualizarStatus($ordem, 'pendente');
    }

    // -------------------------------------------------------------------------
    // listar()
    // -------------------------------------------------------------------------

    public function test_listar_retorna_paginado(): void
    {
        OrdemProducao::factory()->count(5)->create();

        $resultado = $this->service->listar(['por_pagina' => 3]);

        $this->assertInstanceOf(LengthAwarePaginator::class, $resultado);
        $this->assertSame(3, $resultado->count());
        $this->assertSame(5, $resultado->total());
    }

    public function test_listar_filtra_por_status(): void
    {
        OrdemProducao::factory()->count(3)->create(['status' => 'pendente']);
        OrdemProducao::factory()->count(2)->cancelada()->create();

        $resultado = $this->service->listar(['status' => 'pendente']);

        $this->assertSame(3, $resultado->total());
    }

    public function test_listar_filtra_por_busca(): void
    {
        OrdemProducao::factory()->create([
            'sku'               => 'SKU-UNICO',
            'descricao_produto' => 'Produto Alpha',
            'quantidade'        => 100,
        ]);

        OrdemProducao::factory()->count(3)->create([
            'sku'               => 'OTHER-PROD',
            'descricao_produto' => 'Produto Beta',
        ]);

        $resultado = $this->service->listar(['busca' => 'UNICO']);

        $this->assertSame(1, $resultado->total());
    }

    public function test_listar_filtra_por_linha_id(): void
    {
        $linha    = Linha::factory()->create();
        $outra    = Linha::factory()->create();

        OrdemProducao::factory()->count(2)->create(['linha_id' => $linha->id]);
        OrdemProducao::factory()->count(3)->create(['linha_id' => $outra->id]);

        $resultado = $this->service->listar(['linha_id' => $linha->id]);

        $this->assertSame(2, $resultado->total());
    }
}
