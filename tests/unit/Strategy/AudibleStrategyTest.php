<?php
declare(strict_types=1);

namespace GibsonOS\Test\Unit\Archivist\Strategy;

use Codeception\Test\Unit;
use GibsonOS\Core\Dto\Web\Body;
use GibsonOS\Core\Dto\Web\Request;
use GibsonOS\Core\Dto\Web\Response;
use GibsonOS\Core\Enum\HttpStatusCode;
use GibsonOS\Core\Service\CryptService;
use GibsonOS\Core\Service\FfmpegService;
use GibsonOS\Core\Service\FileService;
use GibsonOS\Core\Service\ProcessService;
use GibsonOS\Core\Service\WebService;
use GibsonOS\Module\Archivist\Collector\AudibleFileCollector;
use GibsonOS\Module\Archivist\Factory\Request\AudibleRequestFactory;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Strategy\AudibleStrategy;
use GibsonOS\Test\Unit\Core\ModelManagerTrait;
use phpmock\phpunit\PHPMock;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\NullLogger;

class AudibleStrategyTest extends Unit
{
    use ProphecyTrait;
    use PHPMock;
    use ModelManagerTrait;

    private AudibleStrategy $audibleStrategy;

    private ObjectProphecy|WebService $webService;

    private ObjectProphecy|FfmpegService $ffmpegService;

    private ObjectProphecy|ProcessService $processService;

    private ObjectProphecy|CryptService $cryptService;

    private ObjectProphecy|AudibleFileCollector $audibleFileCollector;

    private ObjectProphecy|AudibleRequestFactory $audibleRequestFactory;

    private ObjectProphecy|FileService $fileService;

    protected function _before(): void
    {
        $this->loadModelManager();
        putenv('TIMEZONE=Europe/Berlin');
        putenv('DATE_LATITUDE=51.2642156');
        putenv('DATE_LONGITUDE=6.8001438');
        $this->webService = $this->prophesize(WebService::class);
        $this->cryptService = $this->prophesize(CryptService::class);
        $this->ffmpegService = $this->prophesize(FfmpegService::class);
        $this->processService = $this->prophesize(ProcessService::class);
        $this->audibleFileCollector = $this->prophesize(AudibleFileCollector::class);
        $this->audibleRequestFactory = $this->prophesize(AudibleRequestFactory::class);
        $this->fileService = $this->prophesize(FileService::class);

        $this->audibleStrategy = new AudibleStrategy(
            $this->webService->reveal(),
            new NullLogger(),
            $this->cryptService->reveal(),
            $this->modelManager->reveal(),
            $this->ffmpegService->reveal(),
            $this->processService->reveal(),
            $this->audibleFileCollector->reveal(),
            $this->audibleRequestFactory->reveal(),
            $this->fileService->reveal(),
        );
    }

    public function testGetExecuteParametersNoLogin(): void
    {
        $this->assertCount(
            1,
            $this->audibleStrategy->getExecuteParameters(new Account($this->modelWrapper->reveal())),
        );
    }

    public function testGetExecuteParametersLoginFalse(): void
    {
        $account = new Account($this->modelWrapper->reveal());
        $account->setExecutionParameters(['login' => false]);

        $this->assertCount(
            1,
            $this->audibleStrategy->getExecuteParameters($account),
        );
    }

    public function testGetExecuteParameters(): void
    {
        $account = new Account($this->modelWrapper->reveal());
        $account->setExecutionParameters(['login' => true]);

        $this->assertCount(
            0,
            $this->audibleStrategy->getExecuteParameters($account),
        );
    }

    public function testSetExecuteParametersNoCookiesJar(): void
    {
        $account = new Account($this->modelWrapper->reveal());

        $this->assertFalse($this->audibleStrategy->setExecuteParameters($account, []));
        $this->assertEquals(['login' => false], $account->getExecutionParameters());
        $this->assertEquals([], $account->getConfiguration());
    }

    public function testSetExecuteParametersCookiesJarParameter(): void
    {
        $this->cryptService->decrypt('marvin')
            ->shouldNotBeCalled()
        ;
        $this->cryptService->encrypt('marvin')
            ->shouldBeCalledOnce()
            ->willReturn('galaxy')
        ;
        $this->cryptService->decrypt('galaxy')
            ->shouldBeCalledOnce()
            ->willReturn('marvin')
        ;
        $request = (new Request('https://www.audible.de/library'))->setCookieFile('marvin');
        $this->audibleRequestFactory->getRequest('https://www.audible.de/library', 'marvin')
            ->shouldBeCalledOnce()
            ->willReturn($request)
        ;
        $this->webService->get($request)
            ->shouldBeCalledOnce()
            ->willReturn(new Response($request, HttpStatusCode::OK, [], (new Body())->setContent(' ', 1), 'marvin'))
        ;
        $account = new Account($this->modelWrapper->reveal());

        $this->assertFalse($this->audibleStrategy->setExecuteParameters($account, ['cookiesJar' => 'marvin']));
        $this->assertEquals(['login' => false], $account->getExecutionParameters());
        $this->assertEquals(['cookiesJar' => 'galaxy'], $account->getConfiguration());
    }

    public function testSetExecuteParametersCookiesJarConfiguration(): void
    {
        $this->cryptService->decrypt('galaxy')
            ->shouldBeCalledTimes(2)
            ->willReturn('marvin')
        ;
        $this->cryptService->encrypt('marvin')
            ->shouldBeCalledOnce()
            ->willReturn('galaxy')
        ;
        $request = (new Request('https://www.audible.de/library'))->setCookieFile('marvin');
        $this->audibleRequestFactory->getRequest('https://www.audible.de/library', 'marvin')
            ->shouldBeCalledOnce()
            ->willReturn($request)
        ;
        $this->webService->get($request)
            ->shouldBeCalledOnce()
            ->willReturn(new Response($request, HttpStatusCode::OK, [], (new Body())->setContent(' ', 1), 'marvin'))
        ;
        $account = (new Account($this->modelWrapper->reveal()))
            ->setConfiguration(['cookiesJar' => 'galaxy'])
        ;

        $this->assertFalse($this->audibleStrategy->setExecuteParameters($account, []));
        $this->assertEquals(['login' => false], $account->getExecutionParameters());
        $this->assertEquals(['cookiesJar' => 'galaxy'], $account->getConfiguration());
    }

    public function testSetExecuteParametersCookiesJarParameterAndConfiguration(): void
    {
        $this->cryptService->decrypt('galaxy')
            ->shouldBeCalledOnce()
            ->willReturn('marvin')
        ;
        $this->cryptService->decrypt('ford')
            ->shouldNotBeCalled()
        ;
        $this->cryptService->encrypt('marvin')
            ->shouldBeCalledOnce()
            ->willReturn('galaxy')
        ;
        $request = (new Request('https://www.audible.de/library'))->setCookieFile('marvin');
        $this->audibleRequestFactory->getRequest('https://www.audible.de/library', 'marvin')
            ->shouldBeCalledOnce()
            ->willReturn($request)
        ;
        $this->webService->get($request)
            ->shouldBeCalledOnce()
            ->willReturn(new Response($request, HttpStatusCode::OK, [], (new Body())->setContent(' ', 1), 'marvin'))
        ;
        $account = (new Account($this->modelWrapper->reveal()))
            ->setConfiguration(['cookiesJar' => 'ford'])
        ;

        $this->assertFalse($this->audibleStrategy->setExecuteParameters($account, ['cookiesJar' => 'marvin']));
        $this->assertEquals(['login' => false], $account->getExecutionParameters());
        $this->assertEquals(['cookiesJar' => 'galaxy'], $account->getConfiguration());
    }

    public function testSetExecuteParametersSuccess(): void
    {
        $this->cryptService->decrypt('galaxy')
            ->shouldBeCalledOnce()
            ->willReturn('marvin')
        ;
        $this->cryptService->decrypt('ford')
            ->shouldNotBeCalled()
        ;
        $this->cryptService->encrypt('marvin')
            ->shouldBeCalledOnce()
            ->willReturn('galaxy')
        ;
        $request = (new Request('https://www.audible.de/library'))->setCookieFile('marvin');
        $this->audibleRequestFactory->getRequest('https://www.audible.de/library', 'marvin')
            ->shouldBeCalledOnce()
            ->willReturn($request)
        ;
        $content = '<h1 class="bc-heading
    bc-color-base
    
    
    
    
    bc-size-display 
    
    bc-text-bold 
    
    
    
    
    bc-text-normal">
                Bibliothek
            </h1>';
        $this->webService->get($request)
            ->shouldBeCalledOnce()
            ->willReturn(new Response($request, HttpStatusCode::OK, [], (new Body())->setContent($content, mb_strlen($content)), 'marvin'))
        ;
        $account = (new Account($this->modelWrapper->reveal()))
            ->setConfiguration(['cookiesJar' => 'ford'])
        ;

        $this->assertTrue($this->audibleStrategy->setExecuteParameters($account, ['cookiesJar' => 'marvin']));
        $this->assertEquals(['login' => true], $account->getExecutionParameters());
        $this->assertEquals(['cookiesJar' => 'galaxy'], $account->getConfiguration());
    }

    public function testGetFiles(): void
    {
        $account = (new Account($this->modelWrapper->reveal()))->setConfiguration(['cookiesJar' => 'galaxy']);
        $request = (new Request('https://www.audible.de/library'))->setCookieFile('marvin');
        $content = 'refinementFormLink    >42';
        $response = new Response($request, HttpStatusCode::OK, [], (new Body())->setContent($content, mb_strlen($content)), 'marvin');

        $this->cryptService->decrypt('galaxy')
            ->shouldBeCalledTimes(43)
            ->willReturn('marvin')
        ;
        $this->audibleRequestFactory->getRequest('https://www.audible.de/library', 'marvin')
            ->shouldBeCalledOnce()
            ->willReturn($request)
        ;
        $this->webService->get($request)
            ->shouldBeCalledOnce()
            ->willReturn($response)
        ;
        $content = ' ';

        for ($i = 1; $i <= 42; ++$i) {
            $request = (new Request('https://www.audible.de/library?page=' . $i))->setCookieFile('marvin');
            $response = new Response($request, HttpStatusCode::OK, [], (new Body())->setContent($content, mb_strlen($content)), 'marvin');
            $this->audibleRequestFactory->getRequest('https://www.audible.de/library?page=' . $i, 'marvin')
                ->shouldBeCalledOnce()
                ->willReturn($request)
            ;
            $this->webService->get($request)
                ->shouldBeCalledOnce()
                ->willReturn($response)
            ;
        }

        $this->audibleFileCollector->getFilesFromPage($account, $content)
            ->shouldBeCalledTimes(42)
            ->willYield([null])
        ;

        iterator_to_array($this->audibleStrategy->getFiles($account));
    }

    public function testGetFilesNoPage(): void
    {
        $account = (new Account($this->modelWrapper->reveal()))->setConfiguration(['cookiesJar' => 'galaxy']);
        $request = (new Request('https://www.audible.de/library'))->setCookieFile('marvin');
        $content = 'arthur dent';
        $response = new Response($request, HttpStatusCode::OK, [], (new Body())->setContent($content, mb_strlen($content)), 'marvin');

        $this->cryptService->decrypt('galaxy')
            ->shouldBeCalledOnce()
            ->willReturn('marvin')
        ;
        $this->audibleRequestFactory->getRequest('https://www.audible.de/library', 'marvin')
            ->shouldBeCalledOnce()
            ->willReturn($request)
        ;
        $this->webService->get($request)
            ->shouldBeCalledOnce()
            ->willReturn($response)
        ;

        $this->assertCount(0, iterator_to_array($this->audibleStrategy->getFiles($account)));
    }
}
