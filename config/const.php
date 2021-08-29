<?php

define('PID_FILE_BASE_PATH', '/data/run/');
define('PARENT_PID_FILE_PATH', PID_FILE_BASE_PATH. 'parent.pid');
define('CHILD_PID_FILE_PATH', PID_FILE_BASE_PATH. 'child.pid');
define('GCHILD_PID_FILE_PATH', PID_FILE_BASE_PATH. 'gchild_%s.pid');
define('CHILD_PROC_INTERVAL', 20);
define('CHILD_PROC_TIMEOUT', 15);
define('GCHILD_PROC_TIMEOUT', 10);

return [];
