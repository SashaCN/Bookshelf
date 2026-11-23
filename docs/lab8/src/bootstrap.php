<?php

declare(strict_types=1);

// Loads the classes of the lab (no Composer needed).
foreach (['Hash', 'ChainTable', 'TypeTable', 'ConstantTable', 'OperationTable', 'IdentifierTable', 'LabelTable',
    'ProcedureTable', 'Tables', 'Scanner'] as $class) {
    require_once __DIR__ . "/$class.php";
}
