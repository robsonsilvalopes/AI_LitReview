@php
  $width = $width ?? '30';
  $height = $height ?? '24';
@endphp

<span class="text-primary">
  <svg width="{{ $width }}" height="{{ $height }}" viewBox="0 0 200 200" fill="none"
    xmlns="http://www.w3.org/2000/svg">
    <!-- Book/Document Base -->
    <rect x="40" y="20" width="120" height="160" rx="4" 
      fill="currentColor" fill-opacity="0.1" stroke="currentColor" stroke-width="3"/>
    
    <!-- Book Pages Lines -->
    <line x1="50" y1="40" x2="150" y2="40" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <line x1="50" y1="60" x2="150" y2="60" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <line x1="50" y1="80" x2="130" y2="80" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <line x1="50" y1="100" x2="140" y2="100" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    
    <!-- AI Symbol - Neural Network Nodes -->
    <circle cx="100" cy="120" r="10" fill="currentColor"/>
    <circle cx="75" cy="140" r="7" fill="currentColor"/>
    <circle cx="125" cy="140" r="7" fill="currentColor"/>
    <circle cx="100" cy="160" r="7" fill="currentColor"/>
    
    <!-- Neural Network Connections -->
    <line x1="100" y1="130" x2="75" y2="133" stroke="currentColor" stroke-width="2"/>
    <line x1="100" y1="130" x2="125" y2="133" stroke="currentColor" stroke-width="2"/>
    <line x1="75" y1="147" x2="100" y2="153" stroke="currentColor" stroke-width="2"/>
    <line x1="125" y1="147" x2="100" y2="153" stroke="currentColor" stroke-width="2"/>
    
    <!-- Search/Review Icon (Lupa) -->
    <circle cx="150" cy="50" r="15" fill="none" stroke="currentColor" stroke-width="3"/>
    <line x1="160" y1="60" x2="170" y2="70" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
  </svg>
</span>
