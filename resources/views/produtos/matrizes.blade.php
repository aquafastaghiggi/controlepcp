@extends('layouts.app')

@section('titulo', 'Matrizes de Setup')

@section('conteudo')
    <div class="p-6">
        <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-900">Matrizes de Setup</h2>
            <p class="text-sm text-gray-500 mt-0.5">Tempos de troca entre SKUs por linha de produção</p>
        </div>

        <livewire:produto.matriz-setup-grid />
    </div>
@endsection
