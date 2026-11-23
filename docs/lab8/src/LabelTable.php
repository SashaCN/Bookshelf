<?php

declare(strict_types=1);

namespace Lab8;

/**
 * The label table for `goto`. A label is visible only inside its function, so the table holds the labels of the
 * function that is being translated; there are few of them (usually 0 to 5), so it is an unordered array searched
 * from the start.
 *
 * A `goto` can come before the label (a forward reference). Then the label is created as undefined and the address
 * of the jump instruction is saved in `refs`. When the label is defined, every saved instruction is fixed up.
 *
 * Record: name, defined (bool), kind (goto|generated), addr (address of the instruction the label points to),
 * line (where it was defined), refs (addresses of the jump instructions that wait for the label).
 */
final class LabelTable
{
    /** @var array<int,array> the labels of the function that is being translated */
    private array $labels = [];
    /** @var array[] the labels of the outer functions (a closure inside a function) */
    private array $outer = [];
    public int $searches = 0;
    public int $probes = 0;

    /** Search by name. Returns the position in the table or null. */
    public function find(string $name): ?int
    {
        $this->searches++;
        foreach ($this->labels as $i => $label) {
            $this->probes++;
            if ($label['name'] === $name) {
                return $i;
            }
        }
        return null;
    }

    /**
     * The `goto` statement: $at is the address of the jump instruction. If the label is already defined, its address
     * is returned. If not, the label is created as undefined, $at is saved for the fix-up and null is returned.
     */
    public function reference(string $name, int $at): ?int
    {
        $i = $this->find($name);
        if ($i === null) {
            $this->labels[] = ['name' => $name, 'defined' => false, 'kind' => 'goto', 'addr' => null, 'line' => null, 'refs' => [$at]];
            return null;
        }
        if ($this->labels[$i]['defined']) {
            return $this->labels[$i]['addr'];
        }
        $this->labels[$i]['refs'][] = $at;
        return null;
    }

    /** Definition `name:`. Returns the addresses of the instructions that must be fixed up (empty when none). */
    public function define(string $name, int $addr, int $line = 0, string $kind = 'goto'): array
    {
        $i = $this->find($name);
        if ($i === null) {
            $this->labels[] = ['name' => $name, 'defined' => true, 'kind' => $kind, 'addr' => $addr, 'line' => $line, 'refs' => []];
            return [];
        }
        $this->labels[$i]['defined'] && throw new \LogicException("Label $name is already defined");
        $fixups = $this->labels[$i]['refs'];
        $this->labels[$i] = ['defined' => true, 'kind' => $kind, 'addr' => $addr, 'line' => $line, 'refs' => []] + $this->labels[$i];
        return $fixups;
    }

    /** Puts the address into the saved jump instructions: $code[$at]['target'] = $addr. */
    public static function patch(array &$code, array $fixups, int $addr): void
    {
        foreach ($fixups as $at) {
            $code[$at]['target'] = $addr;
        }
    }

    public function read(string $name, ?string $attr = null): mixed
    {
        $i = $this->find($name) ?? throw new \OutOfBoundsException("No label $name");
        return $attr === null ? $this->labels[$i] : $this->labels[$i][$attr];
    }

    public function write(string $name, string $attr, mixed $value): void
    {
        $i = $this->find($name) ?? throw new \OutOfBoundsException("No label $name");
        $this->labels[$i][$attr] = $value;
    }

    /** Start of a function body: it gets its own empty set of labels, the labels of the outer function wait. */
    public function openFunction(): void
    {
        $this->outer[] = $this->labels;
        $this->labels = [];
    }

    /** End of the function: its labels are deleted. Returns the names of the labels that were used but never defined. */
    public function closeFunction(): array
    {
        $undefined = array_values(array_map(fn (array $l) => $l['name'], array_filter($this->labels, fn (array $l) => !$l['defined'])));
        $this->labels = array_pop($this->outer) ?? [];
        return $undefined;
    }

    /** Reorganization: the labels are sorted by name (the order of a listing); the array is packed. */
    public function reorganize(): void
    {
        usort($this->labels, fn (array $a, array $b) => strcmp($a['name'], $b['name']));
    }

    /** @return array[] */
    public function all(): array
    {
        return $this->labels;
    }

    public function count(): int
    {
        return count($this->labels);
    }

    public function resetCounters(): void
    {
        $this->searches = $this->probes = 0;
    }
}
