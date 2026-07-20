<?php

declare(strict_types=1);

namespace App\Livewire\Sopro;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

class GerenciarFotosFrascos extends Component
{
    public string $filtro     = 'pendentes'; // pendentes | com_foto | todos
    public string $busca      = '';
    public string $mensagem   = '';
    public string $tipoMensagem = '';

    public array $arquivos = [];

    // Limiar mínimo de similaridade (0-100) pra um PNG ser considerado candidato.
    private const LIMIAR_SIMILARIDADE = 55.0;

    public function mount(): void
    {
        $this->carregarArquivos();
    }

    public function carregarArquivos(): void
    {
        $pastaFotos = public_path('Frascos sem rótulo/');
        $this->arquivos = file_exists($pastaFotos)
            ? array_values(array_filter(
                scandir($pastaFotos),
                fn($f) => preg_match('/\.(png|jpg|jpeg)$/i', $f)
              ))
            : [];
    }

    public function aprovar(int $id, string $arquivo): void
    {
        DB::table('frascos')->where('id', $id)->update(['foto' => $arquivo]);
        $this->mensagem     = 'Foto associada!';
        $this->tipoMensagem = 'success';
    }

    public function rejeitar(int $id): void
    {
        DB::table('frascos')->where('id', $id)->update(['foto' => null]);
        $this->mensagem     = 'Foto removida.';
        $this->tipoMensagem = 'warning';
    }

    public function salvarFoto(int $id, string $arquivo): void
    {
        if (!$arquivo) return;
        DB::table('frascos')->where('id', $id)->update(['foto' => $arquivo]);
        $this->mensagem     = 'Foto salva!';
        $this->tipoMensagem = 'success';
    }

    /**
     * Normaliza um texto (descrição de frasco ou nome de arquivo) pra comparação:
     * lowercase, sem acento, decimal com vírgula -> ponto, sem palavras de ruído
     * ("frasco", "aquafast" e a variante com erro de digitação "aquafat"), sem
     * pontuação, espaços colapsados.
     */
    private function normalizar(string $texto): string
    {
        $texto = preg_replace('/\.(png|jpg|jpeg)$/i', '', $texto) ?? $texto;
        $texto = mb_strtolower($texto, 'UTF-8');

        // Decimal com vírgula (ex.: "1,5l") -> ponto, antes de mexer em pontuação
        $texto = preg_replace('/(\d),(\d)/', '$1.$2', $texto) ?? $texto;

        // Remove acentos via tabela explícita (mais previsível entre ambientes que iconv//TRANSLIT)
        $comAcento = ['á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','ñ'];
        $semAcento = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'];
        $texto = str_replace($comAcento, $semAcento, $texto);

        // Palavras de ruído que aparecem em um lado (banco ou arquivo) mas não
        // ajudam a diferenciar produtos
        $texto = str_replace(['frasco', 'aquafast', 'aquafat'], ' ', $texto);

        // Remove pontuação (mantém letras, números, ponto decimal já normalizado, espaço)
        $texto = preg_replace('/[^a-z0-9. ]+/', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    /**
     * Extrai o token de volume (ex.: "2l", "500ml", "1.5l") de um texto já normalizado.
     * Retorna null se não encontrar nenhum volume reconhecível.
     */
    private function extrairVolume(string $normalizado): ?string
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*(ml|l)\b/', $normalizado, $m)) {
            return $m[1] . $m[2];
        }
        return null;
    }

    private function sugerirFoto(string $descricao): ?string
    {
        $descNorm = $this->normalizar($descricao);
        $volDesc  = $this->extrairVolume($descNorm);

        $melhorArquivo = null;
        $melhorScore   = 0.0;

        foreach ($this->arquivos as $arquivo) {
            $nomeNorm   = $this->normalizar($arquivo);
            $volArquivo = $this->extrairVolume($nomeNorm);

            // Se os dois lados têm um volume detectável, ele precisa bater —
            // evita cruzar "500ml" com "5L" só por causa de palavras em comum.
            if ($volDesc !== null && $volArquivo !== null && $volDesc !== $volArquivo) {
                continue;
            }

            similar_text($descNorm, $nomeNorm, $percentual);
            if ($percentual >= self::LIMIAR_SIMILARIDADE && $percentual > $melhorScore) {
                $melhorScore   = $percentual;
                $melhorArquivo = $arquivo;
            }
        }

        return $melhorArquivo;
    }

    public function render(): \Illuminate\View\View
    {
        $query = DB::table('frascos')->orderBy('descricao');

        if ($this->busca) {
            $query->where(function ($q) {
                $q->where('descricao', 'like', '%' . $this->busca . '%')
                  ->orWhere('sku', 'like', '%' . $this->busca . '%');
            });
        }

        if ($this->filtro === 'com_foto') {
            $query->whereNotNull('foto')->where('foto', '!=', '');
        } elseif ($this->filtro === 'pendentes') {
            $query->where(function ($q) {
                $q->whereNull('foto')->orWhere('foto', '=', '');
            });
        }

        $frascos = $query->get(['id', 'sku', 'descricao', 'foto'])->map(function ($f) {
            $f->sugestao = empty($f->foto) ? $this->sugerirFoto($f->descricao) : null;
            return $f;
        });

        $totalComFoto  = DB::table('frascos')->whereNotNull('foto')->where('foto', '!=', '')->count();
        $totalSemFoto  = DB::table('frascos')->where(function ($q) {
            $q->whereNull('foto')->orWhere('foto', '=', '');
        })->count();

        return view('livewire.sopro.gerenciar-fotos-frascos', compact('frascos', 'totalComFoto', 'totalSemFoto'));
    }
}
