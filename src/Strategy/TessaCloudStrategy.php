<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Behat\Mink\Exception\ElementNotFoundException;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Exception\BrowserException;
use GibsonOS\Module\Archivist\Exception\StrategyException;

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
     * @throws ElementNotFoundException
     */
    public function saveConfigurationParameters(Strategy $strategy, array $parameters): bool
    {
        $session = $this->browserService->getSession();
        $page = $this->browserService->loadPage($session, self::URL);
        $this->browserService->fillFormFields($page, $parameters);
        $page->pressButton('ext-element-107');
        $element = $this->browserService->waitForElementById($page, 'ext-element-211');

        if (trim($element->getText()) !== 'Neueste Dokumente') {
            throw new StrategyException('Login failed!');
        }

        $strategy->setConfigValue('session', $session);

        return true;
    }

    public function getFiles(Strategy $strategy): array
    {
//        $response = $this->browserService->get(
//            (new Request(self::URL . 'api/documents'))
//                ->setCookieFile($strategy->getConfigValue('cookieFile'))
//        );

        return [];
    }

    public function setFileResource(File $file): File
    {
        return $file;
    }

    public function unload(Strategy $strategy): void
    {
    }
}
