<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Reference;

use App\Domain\Service\Reference\Exception\ReferenceNotFoundException;

class ReferenceDeleteAction extends ReferenceAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                $reference = $this->referenceService->read([
                    'uuid' => $this->resolveArg('uuid'),
                ]);
                $this->referenceService->delete($reference);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:reference:delete', $reference);
            } catch (ReferenceNotFoundException $e) {
                // nothing
            }
        }

        // back to the list the click came from, never to an address the Referer header made up
        $back = (string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH);

        return $this->respondWithRedirect(str_starts_with($back, '/cup/reference/') && $this->isLocalPath($back) ? $back : '/cup');
    }
}
