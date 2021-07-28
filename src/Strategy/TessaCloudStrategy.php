<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Generator;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Exception\BrowserException;
use GibsonOS\Module\Archivist\Exception\StrategyException;
use GibsonOS\Module\Archivist\Model\Rule;

class TessaCloudStrategy extends AbstractWebStrategy
{
    private const URL = 'https://check24.tessa-cloud.de/';

    public function getName(): string
    {
        return 'Tessa Cloud';
    }

    public function getConfigurationParameters(Strategy $strategy): array
    {
        return [
            'userName' => (new StringParameter('E-Mail'))
                ->setInputType(StringParameter::INPUT_TYPE_EMAIL),
            'password' => (new StringParameter('Passwort'))
                ->setInputType(StringParameter::INPUT_TYPE_PASSWORD),
        ];
    }

    /**
     * @throws BrowserException
     * @throws StrategyException
     */
    public function saveConfigurationParameters(Strategy $strategy, array $parameters): bool
    {
        $session = $this->browserService->getSession();
        $page = $this->browserService->loadPage($session, self::URL);
        $button = $this->browserService->waitForButton($session, 'ext-element-107');
        $this->browserService->fillFormFields($session, $parameters);
        $userName = $page->findField('userName');

        if ($userName !== null) {
            $userName->focus();
        }

        $button->press();

        try {
            $element = $this->browserService->waitForLink($session, 'Neueste Dokumente');
            $element->click();
            $element = $this->browserService->waitForElementById($session, 'ext-element-335');
        } catch (BrowserException $e) {
            file_put_contents('/home/gibsonOS/tessa.png', $session->getScreenshot());

            throw new StrategyException('Login failed!');
        }

        if (trim($element->getText()) !== 'Titel') {
            throw new StrategyException('Documents not found!');
        }

        return true;
    }

    public function getFiles(Strategy $strategy, Rule $rule): Generator
    {
        $session = $this->getSession($strategy);

        yield new File('foo', 'bar', $this->dateTimeService->get(), $strategy);
    }

    public function setFileResource(File $file, Rule $rule): File
    {
        return $file;
    }

    public function unload(Strategy $strategy): void
    {
        $session = $this->getSession($strategy);
        $page = $session->getPage();
        $button = $page->findById('ext-element-610');

        if ($button !== null) {
            $button->click();
        }

        $session->stop();
    }

    public function getLockName(Rule $rule): string
    {
        return 'tessaCloud';
    }
}
