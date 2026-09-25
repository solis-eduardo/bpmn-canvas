<span class="lang-switch">
    @foreach (['en' => 'EN', 'pt_BR' => 'PT-BR', 'es' => 'ES'] as $code => $label)
        <form method="POST" action="{{ route('locale.update', $code) }}" style="display:inline">
            @csrf
            <button type="submit" {{ app()->getLocale() === $code ? 'disabled' : '' }}>{{ $label }}</button>
        </form>
    @endforeach
</span>
