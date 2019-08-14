<?php

namespace Application\Core;

use Twig\Extension\AbstractExtension;

class TwigExtension extends AbstractExtension
{
    public function getName()
    {
        return '0x12f';
    }

    public function getFunctions()
    {
        return [
            new \Twig\TwigFunction(
                'form',
                function ($type, $name, $args = []) {
                    return form($type, $name, $args);
                },
                [
                    'is_safe' => ['html'],
                ]
            ),

            // todo посмотреть на это решение еще
            new \Twig\TwigFunction(
                'reference',
                function ($reference, $value = null) {
                    try {
                        $reference = constant(str_replace('/', '\\', $reference));

                        switch ($value) {
                            case null:
                                return $reference;
                                    break;

                            default:
                                return $reference[$value];
                        }

                    } catch (\Exception $e) { /* nothing */ }

                    return $value;
                }
            ),

            new \Twig\TwigFunction(
                'pre',
                function (...$args) {
                    call_user_func_array('pre', $args);
                }
            ),

            new \Twig\TwigFunction(
                'collect',
                function (array $array) {
                    return collect($array);
                }
            ),
        ];
    }
}
