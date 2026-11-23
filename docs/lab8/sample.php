<?php
namespace App\Demo;

const LIMIT = 10;

interface Shape
{
    public function area(): float;
}

abstract class Base implements Shape
{
    public const VERSION = "1.0";
    protected int $id = 0;
    abstract public function name(): string;
}

final class Circle extends Base
{
    private readonly float $r;

    public function __construct(float $r)
    {
        $this->r = $r;
    }

    public function area(): float
    {
        return M_PI * $this->r ** 2;
    }

    public function name(): string
    {
        return 'circle';
    }
}

function total(array $items, int $limit = LIMIT): int
{
    static $calls = 0;
    global $config;
    $sum = 0;
    foreach ($items as $i => $item) {
        if ($sum > $limit) {
            goto done;
        }
        $sum += strlen($item);
    }
    $double = function (int $x) use ($sum): int {
        return $x * 2 + $sum;
    };
    done:
    return $sum ?? $calls;
}

$count = intdiv(10, 3);
$text = sprintf("%d items", $count);
