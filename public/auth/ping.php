<?php
// public/auth/ping.php

require_once __DIR__ . '/../middleware/auth.php';

// If auth middleware succeeds, user is authenticated and last_activity_at is updated
json_response([
    'status' => 'success',
    'message' => 'Heartbeat acknowledged',
    'timestamp' => time()
]);
