<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Behat\Mink\Exception\ElementNotFoundException;
use Generator;
use GibsonOS\Core\Dto\Parameter\AbstractParameter;
use GibsonOS\Core\Dto\Parameter\IntParameter;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Core\Dto\Web\Request;
use GibsonOS\Core\Exception\WebException;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Exception\BrowserException;
use GibsonOS\Module\Archivist\Exception\StrategyException;
use GibsonOS\Module\Archivist\Model\Rule;

class DeutscheBankStrategy extends AbstractWebStrategy
{
    private const URL = 'http://localhost/img/page/db/';

//    private const URL = 'https://meine.deutsche-bank.de/';

    public function getName(): string
    {
        return 'Deutsche Bank';
    }

    /**
     * @return AbstractParameter[]
     */
    public function getConfigurationParameters(Strategy $strategy): array
    {
        return [
            'branch' => (new IntParameter('Filiale'))->setRange(1, 999),
            'account' => (new IntParameter('Konto'))->setRange(1, 9999999),
            'subAccount' => (new IntParameter('Unterkonto'))->setRange(0, 99),
            'pin' => (new StringParameter('PIN'))->setInputType(StringParameter::INPUT_TYPE_PASSWORD),
        ];
    }

    /**
     * @param array<string, string> $parameters
     *
     * @throws BrowserException
     * @throws ElementNotFoundException
     */
    public function saveConfigurationParameters(Strategy $strategy, array $parameters): bool
    {
        $session = $this->browserService->getSession();
        $page = $this->browserService->loadPage($session, self::URL);
        $this->logger->debug('Init page: ' . $page->getContent());
        $this->browserService->fillFormFields($page, $parameters);
        $page->pressButton('Login ausführen');

        try {
            $this->browserService->waitForElementById($page, 'pushTANForm');
        } catch (BrowserException $e) {
            $this->browserService->waitForElementById($page, 'photoTAN');
            $page->clickLink('photoTAN push');
            $this->browserService->waitForElementById($page, 'pushTANForm');
        }

        $page->pressButton('confirmButton');
        $this->browserService->waitForElementById($page, 'iframeContainer');
        $session->switchToIFrame('iframeContainer');
        $this->browserService->waitForElementById($page, 'layoutWrapper');
        $allDocuments = $page->find('named_partial', ['content', 'Alle Dokumente']);

        if ($allDocuments !== null) {
            $allDocuments->click();
        }

        $this->browserService->waitForElementById($page, 'layoutWrapper');

        foreach ($page->findAll('css', '.node-row-actions__action--download') as $downloadAction) {
            $downloadAction->click();
        }

        return true;
    }

    public function getFiles(Strategy $strategy, Rule $rule): Generator
    {
        yield new File('foo', 'bar', $this->dateTimeService->get(), $strategy);
    }

    /**
     * @throws StrategyException
     * @throws WebException
     */
    public function setFileResource(File $file): File
    {
        $responseBody = $this->webService->get(new Request($file->getPath()))->getBody();
        $resource = $responseBody->getResource();

        if ($resource === null) {
            throw new StrategyException('No response!');
        }

        return $file->setResource($resource, $responseBody->getLength());
    }

    /**
     * @throws ElementNotFoundException
     */
    public function unload(Strategy $strategy): void
    {
        $session = $this->getSession($strategy);
        $session->switchToWindow();
        $page = $session->getPage();
        $page->clickLink('Kunden-Logout');
        $session->stop();
    }

    public function getLockName(Rule $rule): string
    {
        return 'db';
    }
}
