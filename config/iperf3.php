<?php

declare(strict_types=1);

return [
    /*
    | Maximum number of bytes accepted from a JSON string, file, or stream. This
    | must be a positive integer. Stream input is bounded while reading, but the
    | complete document is buffered and passed to PHP's native JSON decoder.
    */
    'max_input_bytes' => 64 * 1024 * 1024,
];
