<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Traits\ParameterTrait;
use Psr\Container\ContainerInterface;
use Twig\Extension\ExtensionInterface;

abstract class AbstractExtension implements ExtensionInterface
{
    use ParameterTrait;

    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function getTokenParsers()
    {
        return [];
    }

    public function getNodeVisitors()
    {
        return [];
    }

    public function getFilters()
    {
        return [];
    }

    public function getTests()
    {
        return [];
    }

    public function getFunctions()
    {
        return [];
    }

    public function getOperators()
    {
        return [];
    }
}
