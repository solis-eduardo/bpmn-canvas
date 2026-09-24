<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Diagramas — {{ config('app.name') }}</title>
</head>
<body>
    <p><a href="{{ url('/home') }}">Início</a></p>

    <h1>Diagramas</h1>

    <form method="POST" action="{{ route('diagramas.store') }}">
        @csrf
        <input type="text" name="title" placeholder="Nome do diagrama" required>
        <button type="submit">Criar diagrama</button>
    </form>

    <ul>
        @forelse ($diagramas as $diagrama)
            <li>
                <a href="{{ route('diagramas.show', $diagrama->slug) }}">{{ $diagrama->title }}</a>
                (autor: {{ $diagrama->author->name }})
                @if ($diagrama->hasActiveLock())
                    — em edição por {{ $diagrama->lockedBy->name }}
                @endif
            </li>
        @empty
            <li>Nenhum diagrama criado ainda.</li>
        @endforelse
    </ul>
</body>
</html>
