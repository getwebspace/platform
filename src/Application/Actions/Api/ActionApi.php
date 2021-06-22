<?php declare(strict_types=1);

namespace App\Application\Actions\Api;

use App\Domain\AbstractAction;

abstract class ActionApi extends AbstractAction
{
    protected function isAccessAllowed()
    {
        $access = false;
        $params = [];

        // check access
        switch ($this->parameter('entity_access', 'user')) {
            case 'all':
                // allow access for all
                $access = true;
            // no break

            case 'user':
                if (($user = $this->request->getAttribute('user')) !== null) {
                    // allow access for current user
                    $access = true;
                    $params['user'] = $user->getUuid()->toString();
                }
            // no break

            case 'key':
                if (
                    ($key = $this->request->getHeaderLine('key')) !== null
                    || ($key = $this->request->getParam('key')) !== null
                ) {
                    if (in_array($key, explode(PHP_EOL, $this->parameter('entity_keys', '')), true)) {
                        // allow access for key
                        $access = true;
                        $params['key'] = $key;
                    }
                }
        }

        return $access ? $params : false;
    }
}
