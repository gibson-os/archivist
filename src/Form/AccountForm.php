<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Form;

use GibsonOS\Core\Dto\Form;
use GibsonOS\Core\Dto\Parameter\AutoCompleteParameter;
use GibsonOS\Core\Dto\Parameter\StringParameter;
use GibsonOS\Module\Archivist\AutoComplete\StrategyAutoComplete;

class AccountForm
{
    public function __construct(private readonly StrategyAutoComplete $strategyAutoComplete)
    {
    }

    public function getForm(): Form
    {
        return new Form([
            'name' => new StringParameter('Name'),
            'strategy' => new AutoCompleteParameter('Strategie', $this->strategyAutoComplete),
        ], [
            'save' => new Form\Button('Speichern', 'archivist', 'account', ''),
        ]);
    }
}
