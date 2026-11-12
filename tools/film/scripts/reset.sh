#!/bin/bash
# Fresh demo database, arranged for the camera.
set -e
cd "$(dirname "$0")/../../.."
PHP="${PHP:-php} -d error_reporting=24575 -d memory_limit=1G"
$PHP artisan migrate:fresh --seed --force > /dev/null
$PHP artisan tinker --execute="require base_path('tools/film/scripts/stage.php');"
$PHP artisan optimize:clear > /dev/null
