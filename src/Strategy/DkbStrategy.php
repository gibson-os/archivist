<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\ElementNotFoundException;
use Generator;
use GibsonOS\Core\Dto\Parameter\AbstractParameter;
use GibsonOS\Core\Dto\Parameter\IntParameter;
use GibsonOS\Core\Dto\Parameter\OptionParameter;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Core\Dto\Web\Request;
use GibsonOS\Core\Exception\WebException;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Exception\BrowserException;
use GibsonOS\Module\Archivist\Exception\StrategyException;
use GibsonOS\Module\Archivist\Model\Account;

class DkbStrategy extends AbstractWebStrategy
{
    private const URL = 'https://www.dkb.de';

    private const KEY_STEP = 'step';

    private const STEP_LOGIN = 0;

    private const STEP_TAN = 1;

    private const STEP_PATH = 2;

    public function getName(): string
    {
        return 'DKB';
    }

    /**
     * @throws BrowserException
     * @throws ElementNotFoundException
     *
     * @return AbstractParameter[]
     */
    public function getAccountParameters(Strategy $strategy): array
    {
        return [
            'j_username' => new StringParameter('Anmeldename'),
            'j_password' => (new StringParameter('Passwort'))->setInputType(StringParameter::INPUT_TYPE_PASSWORD),
        ];
        //        if (
        //            $strategy->getConfigurationStep() === self::STEP_LOGIN &&
        //            $strategy->hasConfigurationValue('username') &&
        //            $strategy->hasConfigurationValue('password')
        //        ) {
        //            $this->login($strategy, [
        //                'j_username' => $this->cryptService->decrypt($strategy->getConfigurationValue('username')),
        //                'j_password' => $this->cryptService->decrypt($strategy->getConfigurationValue('password')),
        //            ]);
        //        }
        //
        //        return match ($strategy->getConfigurationStep()) {
        //            self::STEP_TAN => $this->getTanParameters(),
        //            self::STEP_PATH => $this->getPathParameters($strategy),
        //        };
    }

    public function setAccountParameters(Account $account, array $parameters): void
    {
        $configuration = $account->getConfiguration();
        $configuration['username'] = $this->cryptService->encrypt($parameters['j_username']);
        $configuration['password'] = $this->cryptService->encrypt($parameters['j_password']);
        $account->setConfiguration($configuration);

        //        switch ($strategy->getConfigurationStep()) {
        //            case self::STEP_TAN:
        //                $this->validateTan($strategy, $parameters);
        //
        // //                return false;
        // // no break
        //            case self::STEP_PATH:
        //                $strategy->setConfigurationValue('path', $parameters['path']);
        //
        // //                return true;
        // // no break
        //            default:
        //                $this->login($strategy, $parameters);
        //
        // //                return false;
        //        }
    }

    public function getExecuteParameters(Account $account): array
    {
        $executionParameters = $account->getExecutionParameters();

        return match ($executionParameters[self::KEY_STEP] ?? self::STEP_LOGIN) {
            self::STEP_LOGIN => [
                'j_username' => new StringParameter('Anmeldename'),
                'j_password' => (new StringParameter('Passwort'))->setInputType(StringParameter::INPUT_TYPE_PASSWORD),
            ],
            self::STEP_TAN => $this->getTanParameters(),
            self::STEP_PATH => [],
            default => throw new StrategyException(sprintf(
                'Unknown dkb step %s',
                $executionParameters[self::KEY_STEP]
            ))
        };
    }

    public function setExecuteParameters(Account $account, array $parameters): bool
    {
        return true;
    }

    /**
     * @throws ElementNotFoundException
     */
    public function getFiles(Account $account): Generator
    {
        $session = $this->getSession($account);
        $page = $session->getPage();
        // @todo er soll jedes Verzeichnis durchlaufen execution parameter directories
        //        $page->clickLink($account->getConfigurationValue('path'));

        try {
            while (true) {
                yield from $this->getFilesFromPage($account);

                $page->clickLink('Nächste Seite');
            }
        } catch (ElementNotFoundException) {
            // do nothing
        }
    }

    /**
     * @return File[]
     */
    private function getFilesFromPage(Account $account): array
    {
        $session = $this->getSession($account);
        $page = $session->getPage();
        $responseBody = $page->getContent();

        $fileMatches = [[], [], [], [], [], []];
        preg_match_all(
            '/(\d{2})\.(\d{2})\.(\d{4})<\/div>\s*<a.+?href="([^"]*)".+?tid="getMailboxAttachment">([^<]+)/s',
            $responseBody,
            $fileMatches
        );
        $files = [];

        foreach ($fileMatches[5] as $id => $fileName) {
            $files[] = new File(
                $fileName,
                self::URL . html_entity_decode($fileMatches[4][$id]),
                $this->dateTimeService->get($fileMatches[3][$id] . '-' . $fileMatches[2][$id] . '-' . $fileMatches[1][$id]),
                $account
            );
        }

        return $files;
    }

    /**
     * @throws StrategyException
     * @throws WebException
     * @throws DriverException
     */
    public function setFileResource(File $file, Account $account): File
    {
        $account = $file->getAccount();

        if ($account->getStrategy() !== self::class) {
            throw new StrategyException(sprintf(
                'Class name %s is not equal with %s',
                $account->getStrategy(),
                self::class
            ));
        }

        $response = $this->webService->get(
            (new Request($file->getPath()))
                ->setCookieFile($this->browserService->createCookieFile($this->getSession($account)))
        );

        $resource = $response->getBody()->getResource();

        if ($resource === null) {
            throw new StrategyException('File is empty!');
        }

        $file->setResource($resource, $response->getBody()->getLength());

        return $file;
    }

    private function getTanParameters(): array
    {
        return [
            'tan' => (new IntParameter('TAN'))->setRange(0, 999999),
        ];
    }

    private function getPathParameters(Account $account): array
    {
        $configuration = $account->getConfiguration();

        return [
            'path' => new OptionParameter('Verzeichnis', $configuration['directories']),
        ];
    }

    /**
     * @throws BrowserException
     * @throws ElementNotFoundException
     */
    private function login(Strategy $strategy, array $parameters): void
    {
        $session = $this->browserService->getSession();
        //        $page = $this->browserService->loadPage($session, self::URL);
        $page = $this->browserService->loadPage($session, self::URL . '/banking');
        $this->logger->debug('Init page: ' . $page->getContent());
        $this->browserService->fillFormFields($session, $parameters);
        $page->pressButton('Anmelden');

        try {
            $this->browserService->waitForElementById($session, 'tanInputSelector');
        } catch (BrowserException) {
            $page->pressButton('Anmeldung bestätigen');
            $this->browserService->waitForElementById($session, 'tanInputSelector');
        }

        $this->logger->debug('Authenticate page: ' . $page->getContent());
        $strategy
            ->setConfigurationValue('session', serialize($session))
            ->setConfigurationValue('username', $this->cryptService->encrypt($parameters['j_username']))
            ->setConfigurationValue('password', $this->cryptService->encrypt($parameters['j_password']))
            ->setNextConfigurationStep()
        ;
    }

    /**
     * @throws ElementNotFoundException
     */
    public function unload(Account $account): void
    {
        $session = $this->getSession($account);
        $page = $session->getPage();
        $page->clickLink('Abmelden');
        $session->stop();
    }

    /**
     * @throws BrowserException
     * @throws ElementNotFoundException
     */
    private function validateTan(Account $account, array $parameters): void
    {
        $session = $this->getSession($account);
        $page = $session->getPage();
        $this->browserService->fillFormFields($session, ['tan' => $parameters['tan']]);
        $page->pressButton('Anmeldung bestätigen');
        $this->browserService->waitForElementById($session, 'menu_0.4-node');
        $page->clickLink('Postfach');
        $this->browserService->waitForElementById($session, 'welcomeMboTable');

        $links = [[], []];
        preg_match_all('/class="evt-gotoFolder[^>]*>([^<|^\s]*)[^<]*/', $page->getContent(), $links);

        $directories = [];

        foreach ($links[1] as $link) {
            $directories[$link] = $link;
        }

        $executionParameter = $account->getExecutionParameters();
        $executionParameter['directories'] = $directories;
        $account->setExecutionParameters($executionParameter);
    }

    public function getLockName(Account $account): string
    {
        return 'dkb';
    }
}
