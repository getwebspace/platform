<?php declare(strict_types=1);

namespace App\Domain\Traits;

trait MacroableTrait
{
    /**
     * The registered string macros.
     */
    protected static array $macros = [];

    /**
     * Mix another object into the class.
     *
     * @throws \ReflectionException
     */
    public static function mixin(object $mixin): void
    {
        $methods = (new \ReflectionClass($mixin))->getMethods(
            \ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_PROTECTED
        );
        foreach ($methods as $method) {
            $method->setAccessible(true);
            static::macro($method->name, $method->invoke($mixin));
        }
    }

    /**
     * Register a custom macro.
     *
     * @param callable|object $macro
     */
    public static function macro(string $name, $macro): void
    {
        static::$macros[$name] = $macro;
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @throws \BadMethodCallException
     *
     * @return mixed
     */
    public static function __callStatic(string $method, array $parameters)
    {
        if (!static::hasMacro($method)) {
            throw new \BadMethodCallException(sprintf(
                'Method %s::%s does not exist.',
                static::class,
                $method
            ));
        }
        if (static::$macros[$method] instanceof \Closure) {
            return call_user_func_array(\Closure::bind(static::$macros[$method], null, static::class), $parameters);
        }

        return call_user_func_array(static::$macros[$method], $parameters);
    }

    /**
     * Checks if macro is registered.
     */
    public static function hasMacro(string $name): bool
    {
        return isset(static::$macros[$name]);
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @throws \BadMethodCallException
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (!static::hasMacro($method)) {
            throw new \BadMethodCallException(sprintf(
                'Method %s::%s does not exist.',
                static::class,
                $method
            ));
        }
        $macro = static::$macros[$method];
        if ($macro instanceof \Closure) {
            return call_user_func_array($macro->bindTo($this, static::class), $parameters);
        }

        return call_user_func_array($macro, $parameters);
    }
}
