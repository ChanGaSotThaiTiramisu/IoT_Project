<?php

// Queries using this condition must alias the devices table as d.
// Use the database clock, just like the gateway's last_seen = NOW().
const DEVICE_ONLINE_SQL = "(d.status = 'online' AND d.last_seen IS NOT NULL
    AND d.last_seen >= NOW() - INTERVAL 10 SECOND)";
