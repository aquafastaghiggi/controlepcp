@extends('layouts.app')
@section('titulo', 'Calendário — Sopro')
@section('conteudo')
<div class="p-6">
    <div class="mb-6">
        <h2 class="text-xl font-bold text-gray-900">Calendário de Trabalho — Sopro</h2>
        <p class="text-sm text-gray-500 mt-0.5">Configure turnos e paradas programadas por máquina</p>
    </div>
    <livewire:sopro.configuracao-calendario-sopro />
</div>
@endsection
