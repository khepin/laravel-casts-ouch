<?php

declare(strict_types=1);

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../src/timer.php';

class ExpensiveCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        return json_decode($value);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        sleep(1);

        return json_encode($value);
    }
}

class B extends Model
{
    protected $guarded = [];

    protected $casts = [
        'a' => ExpensiveCast::class,
    ];
}

$b = new B(['a' => (object) ['name' => 'bob'], 'b' => 'b']);

Timer::td(fn () => $b->a, 'attribute a'); // attribute a = 1.00 s
Timer::td(fn () => $b->b, 'attribute b'); // attribute b = 1.00 s
Timer::td(fn () => $b->b, 'attribute b'); // attribute b = 1.00 s
