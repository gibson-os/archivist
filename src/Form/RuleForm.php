<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Form;

use GibsonOS\Core\Dto\Form\Button;
use GibsonOS\Core\Dto\Form\ModelFormConfig;
use GibsonOS\Core\Dto\Parameter\BoolParameter;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Core\Dto\Parameter\TextParameter;
use GibsonOS\Core\Form\AbstractModelForm;
use GibsonOS\Core\Model\ModelInterface;
use GibsonOS\Module\Archivist\Dto\Form\RuleFormConfig;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Explorer\Dto\Parameter\DirectoryParameter;
use InvalidArgumentException;

/**
 * @extends AbstractModelForm<Rule>
 */
class RuleForm extends AbstractModelForm
{
    protected function getFields(ModelFormConfig $config): array
    {
        return [
            'name' => (new StringParameter('Name')),
            'observedFilename' => (new StringParameter('Beobachtete Dateinamen')),
            'observedContent' => (new TextParameter('Beobachteter Inhalt')),
            'moveDirectory' => (new DirectoryParameter('Ablage Verzeichnis')),
            'moveFilename' => (new StringParameter('Ablage Dateiname')),
            'active' => (new BoolParameter('Aktiv')),
        ];
    }

    public function getButtons(ModelFormConfig $config): array
    {
        if (!$config instanceof RuleFormConfig) {
            throw new InvalidArgumentException('');
        }

        $parameters = ['accountId' => $config->getAccount()->getId()];
        $rule = $config->getModel();

        if ($rule instanceof ModelInterface) {
            $parameters['id'] = $rule->getId();
        }

        return [
            'save' => new Button(
                'Speichern',
                'archivist',
                'rule',
                '',
                $parameters,
            ),
        ];
    }

    public function supportedModel(): string
    {
        return Rule::class;
    }
}
