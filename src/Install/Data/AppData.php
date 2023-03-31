<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Install\Data;

use Generator;
use GibsonOS\Core\Dto\Install\Success;
use GibsonOS\Core\Exception\Model\SaveError;
use GibsonOS\Core\Exception\Repository\SelectError;
use GibsonOS\Core\Install\AbstractInstall;
use GibsonOS\Core\Service\InstallService;
use GibsonOS\Core\Service\PriorityInterface;
use JsonException;

class AppData extends AbstractInstall implements PriorityInterface
{
    /**
     * @throws SaveError
     * @throws SelectError
     * @throws JsonException
     */
    public function install(string $module): Generator
    {
        $this->addApp('Archivar', 'archivist', 'index', 'index', 'icon_scan');

        yield new Success('Archivist apps installed!');
    }

    public function getPart(): string
    {
        return InstallService::PART_DATA;
    }

    public function getModule(): ?string
    {
        return 'archivist';
    }

    public function getPriority(): int
    {
        return 0;
    }
}
