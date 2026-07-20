@extends('layouts.app')
@section('titulo', 'Programação')
@section('conteudo')
<div class="max-w-2xl mx-auto mt-16">
    <h1 class="text-xl font-bold text-gray-800 mb-2">Programação</h1>
    <p class="text-sm text-gray-500 mb-8">Selecione o módulo que deseja programar.</p>

    <div class="grid grid-cols-2 gap-6">
        <a href="{{ route('programacoes.envase') }}"
           class="flex flex-col items-center justify-center gap-4 bg-white border-2 border-gray-200 hover:border-blue-500 hover:shadow-lg rounded-2xl p-10 transition-all duration-200 group">
            <span class="text-5xl">🏭</span>
            <div class="text-center">
                <p class="text-base font-semibold text-gray-800 group-hover:text-blue-600">Envase</p>
                <p class="text-xs text-gray-400 mt-1">Linhas LN01–LN07</p>
            </div>
        </a>

        <a href="{{ route('programacoes.sopro') }}"
           class="flex flex-col items-center justify-center gap-4 bg-white border-2 border-gray-200 hover:border-blue-500 hover:shadow-lg rounded-2xl p-10 transition-all duration-200 group">
            <span class="text-5xl">🧴</span>
            <div class="text-center">
                <p class="text-base font-semibold text-gray-800 group-hover:text-blue-600">Sopro</p>
                <p class="text-xs text-gray-400 mt-1">Máquinas MAQ01–MAQ10</p>
            </div>
        </a>
    </div>
</div>
@endsection
