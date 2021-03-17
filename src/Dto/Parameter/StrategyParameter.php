<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Dto\Parameter;

use GibsonOS\Core\Dto\Parameter\AutoCompleteParameter;
use GibsonOS\Module\Archivist\AutoComplete\StrategyAutoComplete;

class StrategyParameter extends AutoCompleteParameter
{
    public function __construct(StrategyAutoComplete $autoComplete, string $title = 'Strategie')
    {
        parent::__construct($title, $autoComplete);
    }
}
