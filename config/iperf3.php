<?php

declare(strict_types=1);

return [
    /*
    | Maximum number of bytes accepted from a file or stream. Set to 0 to disable
    | the guard. The parser still performs a single native JSON decode.
    */
    'max_input_bytes' => 64 * 1024 * 1024,
];
