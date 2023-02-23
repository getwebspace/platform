<?php declare(strict_types=1);

namespace App\Application\Actions\Api\v1;

use App\Domain\AbstractAction;
use function App\Application\Actions\Api\mb_strpos;

abstract class ActionApi extends AbstractAction
{
    protected function isAccessAllowed(): array|false
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
                $key = $this->getParam('key');
                if ($key === null) {
                    $key = $this->request->getHeaderLine('key');
                }

                if ($key && mb_strpos($this->parameter('entity_keys', ''), $key) !== false) {
                    // allow access for key
                    $access = true;
                    $params['key'] = $key;
                }
        }

        return $access ? $params : false;
    }
}
