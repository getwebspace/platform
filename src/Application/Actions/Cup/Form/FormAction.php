<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form;

use App\Domain\AbstractAction;
use App\Domain\Service\Form\DataService as FormDataService;
use App\Domain\Service\Form\FormService;
use Psr\Container\ContainerInterface;

abstract class FormAction extends AbstractAction
{
    protected FormService $formService;

    protected FormDataService $formDataService;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->formService = $container->get(FormService::class);
        $this->formDataService = $container->get(FormDataService::class);
    }
}
