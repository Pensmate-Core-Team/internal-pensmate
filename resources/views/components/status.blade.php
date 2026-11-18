@props(['status'])
<span class="status status-{{ $status }}"><span class="status-dot"></span>{{ \App\Models\Content::STATUSES[$status] ?? $status }}</span>
