@props(['type' => 'neutral'])
@php
    $stateClass = match($type) {
        'success', 'active', 'approved', 'published' => 'approved',
        'warning', 'review', 'pending', 'progress' => 'review',
        'danger', 'error', 'rejected' => 'rejected',
        default => 'draft',
    };
@endphp
<span {{ $attributes->class(['status', $stateClass]) }}><i class="state-dot" aria-hidden="true"></i>{{ $slot }}</span>
