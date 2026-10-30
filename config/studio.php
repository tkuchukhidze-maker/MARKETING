<?php

return [
    // Disk for uploaded visuals. On Laravel Cloud attach an object-storage
    // bucket and set STUDIO_ASSETS_DISK to its disk name (e.g. "s3"),
    // because the local disk is wiped on every deploy.
    'assets_disk' => env('STUDIO_ASSETS_DISK', 'local'),
];
