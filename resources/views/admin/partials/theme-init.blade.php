{{-- Apply the theme picked with the sidebar switch before first paint (no flash). No choice saved = follow the system. --}}
<script>try { const t = localStorage.getItem('admin-theme'); if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t; } catch (e) {}</script>
