<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $diagrama->title }} — Em edição</title>
</head>
<body>
    <p><a href="{{ url('/diagramas') }}">Diagramas</a></p>

    <h1>{{ $diagrama->title }}</h1>

    <p>
        Este diagrama está sendo editado por <strong>{{ $lockedBy->name }}</strong> no momento.
        Você não pode abrir o Editor até que a Trava seja liberada.
    </p>

    <form method="POST" action="{{ route('diagramas.fork', $diagrama->slug) }}">
        @csrf
        <button type="submit">Criar uma cópia (Fork) para continuar agora</button>
    </form>
</body>
</html>
