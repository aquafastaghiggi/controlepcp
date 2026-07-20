<?php

declare(strict_types=1);

namespace App\Livewire\Cadastros;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

class GerenciarFotosProdutos extends Component
{
    public string $filtro     = 'pendentes'; // pendentes | com_foto | todos
    public string $busca      = '';
    public string $mensagem   = '';
    public string $tipoMensagem = '';

    public array $arquivos = [];

    public function mount(): void
    {
        $this->carregarArquivos();
    }

    public function carregarArquivos(): void
    {
        $pastaFotos = public_path('fotos-produtos/');
        $this->arquivos = file_exists($pastaFotos)
            ? array_values(array_filter(
                scandir($pastaFotos),
                fn($f) => preg_match('/\.(png|jpg|jpeg)$/i', $f)
              ))
            : [];
    }

    public function aprovar(int $id, string $arquivo): void
    {
        DB::table('produtos')->where('id', $id)->update(['foto' => $arquivo]);
        $this->mensagem     = 'Foto associada!';
        $this->tipoMensagem = 'success';
    }

    public function rejeitar(int $id): void
    {
        DB::table('produtos')->where('id', $id)->update(['foto' => null]);
        $this->mensagem     = 'Foto removida.';
        $this->tipoMensagem = 'warning';
    }

    public function salvarFoto(int $id, string $arquivo): void
    {
        if (!$arquivo) return;
        DB::table('produtos')->where('id', $id)->update(['foto' => $arquivo]);
        $this->mensagem     = 'Foto salva!';
        $this->tipoMensagem = 'success';
    }

    private function sugerirFoto(string $descricao, string $sku): ?string
    {
        // Produtos de terceiros — nunca associar foto
        $terceiros = ['ito', 'myata', 'myara'];
        $descLower = mb_strtolower($descricao, 'UTF-8');
        foreach ($terceiros as $t) {
            if (str_contains($descLower, $t)) return null;
        }

        // Extrair litragem do produto
        preg_match('/(\d+[,.]\d+|\d+)\s*(ml|l|kg|g)\b/i', $descricao, $mLitragem);
        $litragemProd = $mLitragem ? strtolower(preg_replace('/\s+/', '', $mLitragem[0])) : null;

        // Palavras-chave da descrição (sem stopwords)
        $stopWords = ['aquafast', 'cx', 'emb', 'econ', 'kit', 'promo', 'promocional', 'recarga', 'pulverizador', 'squeeze', 'frasco', 'diluido', 'caixa'];
        $palavrasProd = array_values(array_filter(
            explode(' ', preg_replace('/[^a-zA-ZÀ-ÿ\s]/u', ' ', mb_strtolower($descricao, 'UTF-8'))),
            fn($p) => strlen($p) > 2 && !in_array($p, $stopWords)
        ));

        if (empty($palavrasProd)) return null;

        $melhorArquivo = null;
        $melhorScore   = 0;

        foreach ($this->arquivos as $arquivo) {
            $nomeArq = pathinfo($arquivo, PATHINFO_FILENAME);

            // Extrair litragem do arquivo
            preg_match('/(\d+[,.]\d+|\d+)\s*(ml|l|kg|g)\b/i', $nomeArq, $mArqLitragem);
            $litragemArq = $mArqLitragem ? strtolower(preg_replace('/\s+/', '', $mArqLitragem[0])) : null;

            // Palavras do arquivo
            $palavrasArq = array_values(array_filter(
                explode(' ', preg_replace('/[^a-zA-ZÀ-ÿ\s]/u', ' ', mb_strtolower($nomeArq, 'UTF-8'))),
                fn($p) => strlen($p) > 2 && !in_array($p, $stopWords)
            ));

            // Score textual puro (sem litragem)
            $emComum    = count(array_intersect($palavrasProd, $palavrasArq));
            $scoreTxt   = $emComum / max(count($palavrasProd), 1);

            // Litragem só desempata — nunca decide sozinha
            // Exige score textual mínimo de 0.3 antes de considerar litragem
            if ($scoreTxt < 0.3) continue;

            $score = $scoreTxt;

            if ($litragemProd && $litragemArq) {
                $lpNorm = str_replace(',', '.', $litragemProd);
                $laNorm = str_replace(',', '.', $litragemArq);
                if ($lpNorm === $laNorm) {
                    $score += 0.3; // bônus de desempate
                } else {
                    $score -= 0.2; // penalidade leve
                }
            }

            if ($score > $melhorScore) {
                $melhorScore   = $score;
                $melhorArquivo = $arquivo;
            }
        }

        return $melhorScore >= 0.3 ? $melhorArquivo : null;
    }

    public function render(): \Illuminate\View\View
    {
        $query = DB::table('produtos')->orderBy('descricao');

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

        $produtos = $query->get(['id', 'sku', 'descricao', 'foto'])->map(function ($p) {
            $p->sugestao = empty($p->foto) ? $this->sugerirFoto($p->descricao, $p->sku) : null;
            return $p;
        });

        $totalComFoto  = DB::table('produtos')->whereNotNull('foto')->where('foto', '!=', '')->count();
        $totalSemFoto  = DB::table('produtos')->where(function ($q) {
            $q->whereNull('foto')->orWhere('foto', '=', '');
        })->count();

        return view('livewire.gerenciar-fotos-produtos', compact('produtos', 'totalComFoto', 'totalSemFoto'));
    }
}
