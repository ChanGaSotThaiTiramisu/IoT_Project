<?php

function workspace_icon(string $name): string
{
    $paths = [
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'students' => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 5v2"/>',
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'questions' => '<path d="m12 3 10 5-10 5L2 8l10-5Zm-10 9 10 5 10-5M2 16l10 5 10-5"/>',
        'devices' => '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M10 18h4M10 6h4"/>',
        'sessions' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18m-13 5h2m4 0h2"/>',
        'results' => '<path d="M4 3v18h17M9 16v-4m5 4V7m5 9v-7"/>',
        'upload' => '<path d="M12 16V3m-5 5 5-5 5 5M4 15v5a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-5"/>',
        'edit' => '<path d="m15 4 5 5M4 20l5-1L21 7a2 2 0 0 0-5-5L4 14v6Z"/>',
        'arrow' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'spark' => '<path d="m12 2 3 7 7 3-7 3-3 7-3-7-7-3 7-3 3-7Z"/>',
    ];
    return '<svg class="workspace-icon" viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}
