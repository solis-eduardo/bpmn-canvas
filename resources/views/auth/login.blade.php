<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Entrar — {{ config('app.name') }}</title>
</head>
<body>
    <form method="POST" action="/login">
        @csrf
        <label>
            E-mail
            <input type="email" name="email" value="{{ old('email') }}" required autofocus>
        </label>
        @error('email')
            <p role="alert">{{ $message }}</p>
        @enderror

        <label>
            Senha
            <input type="password" name="password" required>
        </label>

        <button type="submit">Entrar</button>
    </form>
</body>
</html>
