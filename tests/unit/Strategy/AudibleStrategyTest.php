<?php
declare(strict_types=1);

namespace GibsonOS\Test\Unit\Archivist\Strategy;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Session;
use DMore\ChromeDriver\ChromeDriver;
use GibsonOS\Core\Service\CryptService;
use GibsonOS\Core\Service\DateTimeService;
use GibsonOS\Core\Service\FfmpegService;
use GibsonOS\Core\Service\ProcessService;
use GibsonOS\Core\Service\WebService;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Service\BrowserService;
use GibsonOS\Module\Archivist\Strategy\AudibleStrategy;
use GibsonOS\Test\Unit\Core\UnitTest;
use phpmock\phpunit\PHPMock;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;

class AudibleStrategyTest extends UnitTest
{
    use ProphecyTrait;
    use PHPMock;

    private AudibleStrategy $audibleStrategy;

    private ObjectProphecy|BrowserService $browserService;

    private ObjectProphecy|WebService $webService;

    private ObjectProphecy|FfmpegService $ffmpegService;

    private ObjectProphecy|ProcessService $processService;

    private ObjectProphecy|CryptService $cryptService;

    protected function _before(): void
    {
        putenv('TIMEZONE=Europe/Berlin');
        putenv('DATE_LATITUDE=51.2642156');
        putenv('DATE_LONGITUDE=6.8001438');
        $this->browserService = $this->prophesize(BrowserService::class);
        $this->webService = $this->prophesize(WebService::class);
        $this->ffmpegService = $this->prophesize(FfmpegService::class);
        $this->processService = $this->prophesize(ProcessService::class);
        $this->cryptService = $this->prophesize(CryptService::class);
        $this->audibleStrategy = new AudibleStrategy(
            $this->browserService->reveal(),
            $this->webService->reveal(),
            $this->prophesize(LoggerInterface::class)->reveal(),
            $this->cryptService->reveal(),
            $this->serviceManager->get(DateTimeService::class),
            $this->modelManager->reveal(),
            $this->ffmpegService->reveal(),
            $this->processService->reveal()
        );
    }

    public function testLogin(): void
    {
        $session = new Session(new ChromeDriver('http://localhost:9222', null, 'chrome://new-tab-page'));
        /** @var ObjectProphecy|DocumentElement $page */
        $page = $this->prophesize(DocumentElement::class);

        $this->browserService->getSession()
            ->shouldBeCalledOnce()
            ->willReturn($session)
        ;
        $this->browserService->getPage($session)
            ->shouldBeCalledOnce()
            ->willReturn($page->reveal())
        ;
        $this->browserService->loadPage($session, 'https://audible.de/')
            ->shouldBeCalledOnce()
            ->willReturn($page->reveal())
        ;
        $page->clickLink('Anmelden')
            ->shouldBeCalledOnce()
        ;
        $this->browserService->waitForElementById($session, 'ap_email')
            ->shouldBeCalledOnce()
            ->willReturn(new NodeElement('foo', $session))
        ;
        $this->cryptService->encrypt('Arthur')
            ->shouldBeCalledOnce()
            ->willReturn('Arthur')
        ;
        $this->cryptService->encrypt('Dent')
            ->shouldBeCalledOnce()
            ->willReturn('Dent')
        ;
        $this->cryptService->decrypt('Arthur')
            ->shouldBeCalledOnce()
            ->willReturn('Arthur')
        ;
        $this->cryptService->decrypt('Dent')
            ->shouldBeCalledOnce()
            ->willReturn('Dent')
        ;
        $this->browserService->fillFormFields($session, [
            'email' => 'Arthur',
            'password' => 'Dent',
        ])
            ->shouldBeCalledOnce()
        ;
        $page->pressButton('signInSubmit')
            ->shouldBeCalledOnce()
        ;
        $this->browserService->waitForLink($session, 'Bibliothek', 30000000)
            ->shouldBeCalledOnce()
            ->willReturn(new NodeElement('foo', $session))
        ;
        $page->clickLink('Bibliothek')
            ->shouldBeCalledOnce()
        ;
        $this->browserService->waitForElementById($session, 'lib-subheader-actions')
            ->shouldBeCalledOnce()
            ->willReturn(new NodeElement('foo', $session))
        ;

        $account = new Account();
        $this->audibleStrategy->setAccountParameters($account, [
            'email' => 'Arthur',
            'password' => 'Dent',
        ]);
        $this->audibleStrategy->setExecuteParameters($account, []);
    }

    public function testLoginWithCaptcha(): void
    {
    }

    /**
     * @dataProvider getData
     */
    public function testGetFiles(string $name, string $content, ?string $subContent): void
    {
        /** @var ObjectProphecy|Session $session */
        $session = $this->prophesize(Session::class);
        /** @var ObjectProphecy|DocumentElement $page */
        $page = $this->prophesize(DocumentElement::class);
        $account = (new Account())->setStrategy(AudibleStrategy::class);

        if ($subContent === null) {
            $page->getContent()->willReturn($content);
        } else {
            $page->getContent()->willReturn($content, $subContent, $content, $content);
            $this->browserService->waitForElementById($session->reveal(), 'lib-subheader-actions')
                ->shouldBeCalledOnce()
                ->willReturn($this->prophesize(NodeElement::class)->reveal())
            ;
            $session->getCurrentUrl()->shouldBeCalledOnce();
            $this->browserService->goto($session->reveal(), Argument::any())->shouldBeCalledOnce();
            $this->modelManager->saveWithoutChildren(Argument::any())->shouldBeCalledOnce();
        }

        $page->getContent()->shouldBeCalledTimes($subContent === null ? 1 : 2);
        $session->getPage()
            ->shouldBeCalledTimes($subContent === null ? 2 : 4)
            ->willReturn($page)
        ;
        $this->browserService->getSession()
            ->shouldBeCalledTimes($subContent === null ? 2 : 4)
            ->willReturn($session)
        ;

        $this->assertSame($name, $this->audibleStrategy->getFiles($account)->current()->getName());
    }

    public function getData(): array
    {
        $testData = [];

        foreach (glob('tests/_data/audible/content/*.content') as $testFile) {
            $testName = str_replace('.content', '', $testFile);
            $subTestFile = $testName . '.subcontent';
            $testNameParts = explode('/', $testName);
            $testName = end($testNameParts);

            $testData[$testName] = [
                $testName,
                file_get_contents($testFile),
                file_exists($subTestFile) ? file_get_contents($subTestFile) : null,
            ];
        }

        return $testData;
    }
}
