<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordens de producao — unidade atomica de trabalho a ser executada em uma linha.
 *
 * Cada ordem referencia um produto (via sku + descricao_produto denormalizados)
 * e pode ser vinculada a uma programacao de sequenciamento. O numero_op e gerado
 * automaticamente apos o insert quando nao informado pelo operador ou pelo ERP.
 *
 * Ciclo de vida:
 *   pendente -> programada -> em_producao -> concluida
 *                  |              |
 *               cancelada     cancelada
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordens_producao', function (Blueprint $table) {
            $table->id();

            // Codigo de identificacao da OP — gerado automaticamente se nao informado
            $table->string('numero_op', 50)->nullable()->unique();

            // Linha de producao onde a ordem sera executada (nullable: OP ainda nao alocada)
            $table->foreignId('linha_id')
                ->nullable()
                ->constrained('linhas')
                ->nullOnDelete();

            // SKU denormalizado — referencia produtos.sku sem FK para preservar historico
            $table->string('sku', 100);

            // Descricao do produto copiada no momento do cadastro para fins de historico
            $table->string('descricao_produto', 255);

            $table->decimal('quantidade', 12, 3);

            // Data-alvo de entrega — usada pelo motor de sequenciamento para calcular urgencia
            $table->date('data_entrega')->nullable();

            // Prioridade: 1 = mais urgente, 10 = menos urgente
            $table->unsignedTinyInteger('prioridade')->default(5);

            $table->enum('status', ['pendente', 'programada', 'em_producao', 'concluida', 'cancelada'])
                ->default('pendente');

            // Rastreia como a ordem entrou no sistema para fins de auditoria
            $table->enum('origem', ['manual', 'excel', 'api_erp'])->default('manual');

            $table->text('observacoes')->nullable();

            // Programacao a qual esta ordem esta associada (nullable: OP ainda nao programada)
            $table->foreignId('programacao_id')
                ->nullable()
                ->constrained('programacoes')
                ->nullOnDelete();

            $table->timestamps();

            // Indices para os filtros mais frequentes
            $table->index('linha_id');
            $table->index('programacao_id');
            $table->index(['status', 'linha_id'], 'ordens_producao_status_linha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordens_producao');
    }
};
