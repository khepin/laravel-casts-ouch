# Expensive Casts Made Infinitely Expensive

This is a repo to showcase an issue we're facing with Laravel's `cast` system where casts that are expensive (slow) to run get called constantly on any access of any other property.

We faced this issue when working with a job that manipulates hundreds, sometimes thousands of related models, all of which have a `payload` property which is a JSON string.
We use a serialization library (https://github.com/square/pjson) to map those payloads to actual PHP classes.

We then run extensive computations on the entire set of objects. But we realized that accessing any model property, as soon as we had previously accessed the `payload` property of a model, took ~1ms to access. Whereas Laravel is more usually around 5µs. Since we're doing thousands of such accesses, our job is taking multiple seconds where we'd expect it to reasonably take under 500ms.

## Example

The src/index.php file contains an example to reproduce the issue:

Expensive cast is a cast class that will just `sleep` for a full second before returning the `string` or database encoded version of the value.

⚠️ The `get` method returns an object. This will make the result of the cast cacheable which ... in turns is the cause for the performance issues!

```php
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
```

The model class is extremely simple and defines the attribute `a` to be castable via `ExpensiveCast`

```php
class B extends Model
{
    protected $guarded = [];

    protected $casts = [
        'a' => ExpensiveCast::class,
    ];
}
```

With this setup, we can create an instance of our model with 2 attributes: `a` being a JSON object that can be cast, and `b` just a string.

Now accessing any attribute on our model will always take an entire second because the `set` method on the cast is called every single time.

```php
$b = new B(['a' => (object) ['name' => 'bob'], 'b' => 'b']);

Timer::td(fn () => $b->a, 'attribute a'); // attribute a = 1.00 s
Timer::td(fn () => $b->b, 'attribute b'); // attribute b = 1.00 s
```

## What's happening

When we call an attribute on a Laravel Model class, it gets caught by the `__get` magic method that then tries to return the correct value from relations and / or attributes.

This will land in the `HasAttributes` trait and in the `getAttributeFromArray` method.

```php
protected function getAttributeFromArray($key)
{
    return $this->getAttributes()[$key] ?? null;
}
```

`getAttributes` here will then attempt to reconstruct the entire view of what the `attributes` array should be. To do so, it will also merge in the cast definitions.

This will happen in `mergeAttributesFromClassCasts`. This method will be called every time any attribute is being accessed on the model and does the following:

```php
protected function mergeAttributesFromClassCasts()
{
    // Loop through all the casts that have been cached
    // Because our cast is relatively expensive to compute (both on the way in and out),
    // we made sure that it was cacheable here, so it will be part of this array
    foreach ($this->classCastCache as $key => $value) {
        // Retrieve the caster class. This will be a new instance every time.
        $caster = $this->resolveCasterClass($key);

        // Merge the existing attributes with the result of the cast's serialization.
        $this->attributes = array_merge(
            $this->attributes,
            $caster instanceof CastsInboundAttributes
                ? [$key => $value]
                : $this->normalizeCastClassResponse($key, $caster->set($this, $key, $value, $this->attributes))
        );
    }
}
```

The important part here is the call to `$caster->set($this, $key, $value, $this->attributes)`
Every time we just try to read an attribute's value, Laravel will ask the `cast` class to first serialize the value to populate the `attributes` array.

Even if the attribute we are trying to access is un-related to the one being serialized.

Even if no attribute has changed in between 2 attribute accesses.

## Solution?

I ... don't have one! I don't know enough about why Laravel is currently trying to re-compute the attribute array on every attribute read. And without that knowledge, I think any "solution" I would have is just likely to break more other things than it would fix.
