<?php
declare(strict_types=1);

namespace Dev256\Framework\Module;

class GetList
{
    /**
     * @return string[]
     */
    public function execute(): array
    {
        return array_diff(scandir(BASEDIR), ['.', '..', 'Framework']);
    }
}
