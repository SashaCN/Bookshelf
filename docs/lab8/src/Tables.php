<?php

declare(strict_types=1);

namespace Lab8;

/** The six tables of the translator in one place. The links between them are ids, not copies. */
final class Tables
{
    public TypeTable $types;
    public IdentifierTable $identifiers;
    public ConstantTable $constants;
    public OperationTable $operations;
    public LabelTable $labels;
    public ProcedureTable $procedures;

    public function __construct(string $hash = 'pjw')
    {
        $this->types = new TypeTable();
        $this->identifiers = new IdentifierTable($hash);
        $this->constants = new ConstantTable($this->types, $hash);
        $this->operations = new OperationTable($this->types, $hash);
        $this->labels = new LabelTable();
        $this->procedures = new ProcedureTable($this->types, $hash);
    }
}
