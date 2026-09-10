<?php

return [
    'hidden_admin_user_ids' => collect(explode(',', (string) env('HIDDEN_ADMIN_USER_IDS', '')))
        ->map(fn ($id) => trim($id))
        ->filter(fn ($id) => $id !== '' && ctype_digit($id))
        ->map(fn ($id) => (int) $id)
        ->values()
        ->all(),
];
