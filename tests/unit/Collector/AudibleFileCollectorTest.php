<?php
declare(strict_types=1);

namespace GibsonOS\Test\Unit\Archivist\Collector;

use Codeception\Test\Unit;
use DateTime;
use GibsonOS\Core\Service\DateTimeService;
use GibsonOS\Module\Archivist\Collector\AudibleFileCollector;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Test\Unit\Core\ModelManagerTrait;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\NullLogger;

class AudibleFileCollectorTest extends Unit
{
    use ProphecyTrait;
    use ModelManagerTrait;

    private AudibleFileCollector $audibleFileCollector;

    private DateTimeService|ObjectProphecy $dateTimeService;

    protected function _before()
    {
        $this->loadModelManager();
        $this->dateTimeService = $this->prophesize(DateTimeService::class);

        $this->audibleFileCollector = new AudibleFileCollector(
            new NullLogger(),
            $this->dateTimeService->reveal(),
        );
    }

    public function testGetFilesFromPage(): void
    {
        $this->dateTimeService->get()
            ->willReturn(new DateTime())
        ;
        $page = file_get_contents('tests/_data/audible/library/page1.html');
        $account = new Account($this->modelWrapper->reveal());

        var_dump(iterator_to_array($this->audibleFileCollector->getFilesFromPage($account, $page)));
    }
}
