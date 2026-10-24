<?php

return [
    // GEOAPI_* settings as seen when the config was built. With a cached config, artisan does not load
    // .env, so legacy code run from the command line reads them from here (legacy/src/env.php).
    'env' => geoapi_env_snapshot(),
];
