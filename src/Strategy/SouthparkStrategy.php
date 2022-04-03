<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Strategy;

use Generator;
use GibsonOS\Core\Dto\Web\Request;
use GibsonOS\Core\Exception\WebException;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Exception\StrategyException;
use GibsonOS\Module\Archivist\Model\Rule;

class SouthparkStrategy extends AbstractWebStrategy
{
    public function getName(): string
    {
        return 'Southpark';
    }

    public function getConfigurationParameters(Strategy $strategy): array
    {
        return [];
    }

    public function saveConfigurationParameters(Strategy $strategy, array $parameters): bool
    {
        return true;
    }

    public function getFiles(Strategy $strategy, Rule $rule): Generator
    {
        foreach ($this->getSessions() as $session) {
            yield new File(
                'Cartman und die Analsonde',
                'https://www.southpark.de/folgen/940f8z/south-park-cartman-und-die-analsonde-staffel-1-ep-1',
                $this->dateTimeService->get(),
                $strategy
            );
        }
    }

    private function getSessions(): Generator
    {
        yield 'https://www.southpark.de/seasons/south-park/yjy8n9/staffel-1';
    }

    /**
     * @throws WebException
     * @throws StrategyException
     */
    public function setFileResource(File $file, Rule $rule): File
    {
        $strategy = $file->getStrategy();

        if ($strategy->getClassName() !== self::class) {
            throw new StrategyException(sprintf(
                'Class name %s is not equal with %s',
                $strategy->getClassName(),
                self::class
            ));
        }

        $fileName = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('Southpark', true) . '.ts';
        $newFile = fopen($fileName, 'wb');
        $offset = 0;

        for ($i = 0; true; ++$i) {
            $url =
                'https://vimn-ns1.ts.mtvnservices.com/v1/gsp.alias/mediabus/2020/05/05/03/15/28/ea6d58cadfdb4ec8b465901dc1a98671/772940/0/seg_1920x1080_4577580_' .
                $i .
                '_1456213782.ts'
            ;
            $response = $this->webService->get(new Request($url));

            if ($response->getStatusCode() !== 200) {
                break;
            }

            $resource = $response->getBody()->getResource();

            if ($resource == null) {
                throw new StrategyException(sprintf('No response for "%s"!', $url));
            }

            $length = $response->getBody()->getLength();
            stream_copy_to_stream($resource, $newFile);
            $offset += $length;
        }

        fclose($newFile);
        $newFile = fopen($fileName, 'r');
        $file->setResource($newFile, $offset);

        return $file;
    }

    public function unload(Strategy $strategy): void
    {
    }

    public function getLockName(Rule $rule): string
    {
        return 'southpark';
    }
}
