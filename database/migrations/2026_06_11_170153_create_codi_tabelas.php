<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codi_performance', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_recurso');
            $table->string('nome_recurso')->nullable();
            $table->string('codigo_item')->nullable();
            $table->string('ordem_producao')->nullable();
            $table->decimal('disponibilidade', 5, 2)->nullable();
            $table->decimal('performance', 5, 2)->nullable();
            $table->decimal('oee', 5, 2)->nullable();
            $table->string('estado_atual')->nullable();
            $table->decimal('quantidade_produzida', 12, 2)->nullable();
            $table->json('dados_raw')->nullable();
            $table->timestamp('sincronizado_em')->nullable();
            $table->timestamps();
            $table->index('codigo_recurso');
            $table->index('ordem_producao');
        });

        Schema::create('codi_eventos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_evento')->unique();
            $table->string('codigo_recurso');
            $table->string('ordem_producao')->nullable();
            $table->string('codigo_item')->nullable();
            $table->enum('tipo_evento', ['PRODUCAO', 'SETUP', 'REJEITO', 'PARADA']);
            $table->decimal('quantidade', 12, 2)->nullable();
            $table->datetime('inicio_evento')->nullable();
            $table->datetime('fim_evento')->nullable();
            $table->integer('duracao_minutos')->nullable();
            $table->json('dados_raw')->nullable();
            $table->timestamps();
            $table->index(['ordem_producao', 'tipo_evento']);
            $table->index('codigo_recurso');
        });

        Schema::create('codi_eficiencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programacao_id')->constrained('programacoes')->cascadeOnDelete();
            $table->string('numero_op');
            $table->string('sku');
            $table->string('codigo_recurso')->nullable();
            $table->decimal('quantidade_programada', 12, 2)->nullable();
            $table->integer('tempo_padrao_minutos')->nullable();
            $table->datetime('inicio_previsto')->nullable();
            $table->datetime('fim_previsto')->nullable();
            $table->decimal('quantidade_realizada', 12, 2)->nullable();
            $table->integer('tempo_real_minutos')->nullable();
            $table->integer('tempo_parado_minutos')->nullable()->default(0);
            $table->datetime('inicio_real')->nullable();
            $table->datetime('fim_real')->nullable();
            $table->decimal('eficiencia_quantidade', 5, 2)->nullable();
            $table->decimal('performance_tempo', 5, 2)->nullable();
            $table->decimal('disponibilidade', 5, 2)->nullable();
            $table->decimal('oee', 5, 2)->nullable();
            $table->decimal('produtividade', 10, 2)->nullable();
            $table->decimal('desvio_quantidade', 12, 2)->nullable();
            $table->decimal('desvio_quantidade_pct', 5, 2)->nullable();
            $table->decimal('desvio_tempo_horas', 8, 2)->nullable();
            $table->integer('desvio_prazo_dias')->nullable();
            $table->enum('status', ['ok', 'aviso', 'critico', 'pendente'])->default('pendente');
            $table->timestamp('calculado_em')->nullable();
            $table->timestamps();
            $table->unique(['programacao_id', 'numero_op']);
            $table->index('numero_op');
        });

        Schema::create('codi_sku_mapping', function (Blueprint $table) {
            $table->id();
            $table->string('sku_codi')->unique();
            $table->string('sku_pcp')->nullable();
            $table->decimal('fator_conversao', 10, 4)->default(1.0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('codi_sincronizacao_log', function (Blueprint $table) {
            $table->id();
            $table->string('tipo');
            $table->enum('status', ['sucesso', 'erro', 'parcial']);
            $table->integer('registros_processados')->default(0);
            $table->integer('registros_novos')->default(0);
            $table->integer('registros_atualizados')->default(0);
            $table->text('erro_mensagem')->nullable();
            $table->integer('duracao_segundos')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codi_eficiencia');
        Schema::dropIfExists('codi_eventos');
        Schema::dropIfExists('codi_performance');
        Schema::dropIfExists('codi_sku_mapping');
        Schema::dropIfExists('codi_sincronizacao_log');
    }
};
