<?php

namespace App\Application\Actions\Api;

use AEngine\Support\Str;
use App\Application\Actions\Action;
use Psr\Container\ContainerInterface;

abstract class ActionApi extends Action
{
    protected function array_criteria_uuid($data) {
        $result = [];

        if (!is_array($data)) $data = [$data];
        foreach ($data as $value) {
            if (\Ramsey\Uuid\Uuid::isValid($value) === true) {
                $result[] = $value;
            }
        }

        return $result;
    }

    protected function array_criteria($data) {
        $result = [];

        if (!is_array($data)) $data = [$data];
        foreach ($data as $value) {
            $result[] = $value;
        }

        return $result;
    }
}
