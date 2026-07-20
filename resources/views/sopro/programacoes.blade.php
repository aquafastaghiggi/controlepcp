@extends('layouts.app')
@section('titulo', 'Programações Sopro')
@section('conteudo')
<div class="max-w-5xl mx-auto">
    @livewire('sopro.lista-programacoes-sopro')
</div>
@endsection
