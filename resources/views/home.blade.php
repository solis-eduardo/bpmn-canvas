<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }}</title>
</head>
<body>
    <p>Olá, {{ auth()->user()->name }}.</p>
    <form method="POST" action="/logout">
        @csrf
        <button type="submit">Sair</button>
    </form>
</body>
</html>
