<?php

declare(strict_types=1);

// Folder-resolved: config/admin/panel.php → config('admin.panel.*').
// No __namespace key: proves the in-file override is optional.
return [
    'title' => 'Admin',
    'items_per_page' => 25,
];
