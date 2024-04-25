<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use App\Domain\Service\Catalog\AttributeService as CatalogAttributeService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\Reference\ReferenceService;
use App\Domain\Service\User\GroupService as UserGroupService;
use App\Domain\Casts\Reference\Type as ReferenceType;

class ParametersPageAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $redirect = $this->getQueryParam('return', '/cup/parameters');
        $parameters = $this->parameter();

        if ($this->isPost()) {
            /** @var ParameterService $parameterService */
            $parameterService = $this->container->get(ParameterService::class);

            foreach ($this->request->getParsedBody() as $group => $params) {
                foreach ($params as $key => $value) {
                    $data = [
                        'name' => trim($group . '_' . $key),
                        'value' => is_array($value) ? implode(',', $value) : $value,
                    ];

                    if (!blank($data['value'])) {
                        if (($parameter = $parameters->firstWhere('name', $data['name'])) !== null) {
                            $parameterService->update($parameter, $data);
                        } else {
                            $parameterService->create($data);
                        }
                    } else {
                        if (($parameter = $parameters->firstWhere('name', $data['name'])) !== null) {
                            $parameterService->delete($parameter);
                        }
                    }
                }
            }

            return $this->respondWithRedirect($redirect);
        }

        $routes = $this->getRoutes();
        $userGroupService = $this->container->get(UserGroupService::class);
        $catalogAttributeService = $this->container->get(CatalogAttributeService::class);
        $referenceService = $this->container->get(ReferenceService::class);

        return $this->respondWithTemplate('cup/parameters/index.twig', [
            'routes' => [
                'all' => $routes->all(),
                'guest' => $routes->filter(fn ($el) => str_starts_with($el, 'common:'))->all(),
            ],
            'groups' => $userGroupService->read(),
            'attributes' => $catalogAttributeService->read(),
            'reference' => $referenceService->read([
                'type' => [ReferenceType::ORDER_STATUS], // todo usage with array of types
                'order' => [
                    'order' => 'asc'
                ]
            ]),
            'parameter' => $parameters,
        ]);
    }
}
