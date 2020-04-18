<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\AbstractAction;
use App\Domain\Entities\User;
use App\Domain\Repository\UserRepository;
use Psr\Container\ContainerInterface;

abstract class UserAction extends AbstractAction
{
    /**
     * @var UserRepository
     */
    protected $users;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->users = $this->entityManager->getRepository(User::class);
    }
}
