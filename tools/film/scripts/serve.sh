#!/bin/bash
# Serves the app for the camera on 127.0.0.1:8030 with PHP's own server, several workers.
cd "$(dirname "$0")/../../../public"
export PHP_CLI_SERVER_WORKERS=6
exec ${PHP:-php} -d error_reporting=24575 -d memory_limit=1G -S 127.0.0.1:8030 -t . ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
