<li>
    <div class="genealogy-node">
        @if(!empty($node['role']))
            <span class="genealogy-role {{ $node['role'] === 'Madre' ? 'madre' : ($node['role'] === 'Padre' ? 'padre' : 'cria') }}">{{ $node['role'] }}</span>
        @endif
        @if(!empty($node['url']))
            <a href="{{ $node['url'] }}" class="genealogy-name">{{ $node['name'] }}</a>
        @else
            <span class="genealogy-name">{{ $node['name'] }}</span>
        @endif
    </div>
    @if(!empty($node['children']))
        <ul>
            @foreach($node['children'] as $child)
                @include('genealogy._node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>