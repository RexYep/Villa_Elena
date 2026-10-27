{{-- One what-if card ($rec: an unsaved Recommendation). No Apply or Dismiss:
     the card is not saved, and its dates may already be over. --}}
<article class="rec-card">
    <div class="rec-kind-icon {{ $rec->action_tag_class }}" aria-hidden="true">
        <i class="bi bi-{{ $rec->action_icon }}"></i>
    </div>
    <div>
        <div class="rec-kind">{{ $rec->action_label }}</div>
        <h3 class="rec-title">{{ $rec->title }}</h3>
        <p class="rec-summary">{{ $rec->summary }}</p>

        @if (!empty($rec->evidence))
            <ul class="rec-facts">
                @foreach ($rec->evidence as $fact)
                    <li>{{ $fact }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</article>
