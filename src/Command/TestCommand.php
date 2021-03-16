<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Command;

use GibsonOS\Core\Command\AbstractCommand;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Strategy\DkbStrategy;
use Psr\Log\LoggerInterface;

class TestCommand extends AbstractCommand
{
    private DkbStrategy $dkbStrategy;

    public function __construct(LoggerInterface $logger, DkbStrategy $dkbStrategy)
    {
        parent::__construct($logger);
        $this->dkbStrategy = $dkbStrategy;
    }

    protected function run(): int
    {
        $strategy = new Strategy(DkbStrategy::class);
        $this->dkbStrategy->authenticate($strategy, ['j_username' => '1035603636_p', 'j_password' => 'R0nj4']);

        return 0;
    }
}
