<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use App\Domain\Service\Catalog\AttributeService as CatalogAttributeService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\User\GroupService as UserGroupService;

class ParametersPageAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $parameters = $this->parameter();

        if ($this->isPost()) {
            $parameterService = $this->container->get(ParameterService::class);

            foreach ($this->request->getParsedBody() as $group => $params) {
                foreach ($params as $key => $value) {
                    $data = [
                        'key' => trim($group . '_' . $key),
                        'value' => is_array($value) ? implode(',', $value) : $value,
                    ];

                    if (($parameter = $parameters->firstWhere('key', $data['key'])) !== null) {
                        $parameterService->update($parameter, $data);
                    } else {
                        $parameterService->create($data);
                    }
                }
            }

            return $this->respondWithRedirect($this->getQueryParam('return', '/cup/parameters'));
        }

        return $this->respondWithTemplate('cup/parameters/index.twig', [
            'timezone' => collect(\DateTimeZone::listIdentifiers())->mapWithKeys(fn ($item) => [$item => $item]),
            'routes' => [
                'all' => $this->getRoutes()->all(),
                'guest' => $this->getRoutes()->filter(fn ($el) => str_starts_with($el, 'common:'))->all(),
            ],
            'groups' => $this->container->get(UserGroupService::class)->read(),
            'attributes' => $this->container->get(CatalogAttributeService::class)->read()->whereNotIn('type', \App\Domain\Types\Catalog\AttributeTypeType::TYPE_BOOLEAN),
            'parameter' => $parameters,
        ]);
    }
}
