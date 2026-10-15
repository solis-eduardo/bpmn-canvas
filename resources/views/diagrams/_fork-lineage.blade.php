{{-- Where a Diagram was forked from and its Forks, each only when visible to the User (CONTEXT.md, "Fork"). --}}
@if ($forkedFrom)
    <div id="info-forked-from">
        <dt>{{ __('messages.forked_from') }}</dt>
        <dd><a href="{{ route('diagrams.show', $forkedFrom->slug) }}" class="underline">{{ $forkedFrom->title }}</a></dd>
    </div>
@endif
@if ($forks->isNotEmpty())
    <div id="info-forks">
        <dt>{{ __('messages.forks_of_this_diagram') }}</dt>
        <dd>
            <ul>
                @foreach ($forks as $fork)
                    <li><a href="{{ route('diagrams.show', $fork->slug) }}" class="underline">{{ $fork->title }}</a></li>
                @endforeach
            </ul>
        </dd>
    </div>
@endif
