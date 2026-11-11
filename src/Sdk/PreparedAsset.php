<?php

declare(strict_types=1);

namespace PhpBaremetal\Spec\Sdk;

/** An asset ready to go to the board: the build tool has already converted it. */
final readonly class PreparedAsset
{
    public function __construct(
        /** the asset storage, e.g. "embedded" or "microsd" */
        public string $storage,
        /** absolute path on the board, e.g. /sdcard/assets/photos/beans.sli */
        public string $boardPath,
        /** absolute local path of the prepared file */
        public string $file,
    ) {
    }
}
