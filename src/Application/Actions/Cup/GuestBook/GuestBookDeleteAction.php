<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\GuestBook;

class GuestBookDeleteAction extends GuestBookAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \App\Domain\Entities\GuestBook $item */
            $item = $this->gbookRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

            if (!$item->isEmpty()) {
                $this->entityManager->remove($item);
                $this->entityManager->flush();
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/guestbook')->withStatus(301);
    }
}
