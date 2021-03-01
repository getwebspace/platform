<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use App\Domain\Service\Catalog\AttributeService as CatalogAttributeService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\User\GroupService as UserGroupService;

class ParametersPageAction extends AbstractAction
{
    protected function action(): \Slim\Http\Response
    {
        $parameters = $this->parameter();

        if ($this->request->isPost()) {
            $parameterService = ParameterService::getWithContainer($this->container);

            foreach ($this->request->getParsedBody() as $group => $params) {
                foreach ($params as $key => $value) {
                    $data = [
                        'key' => $group . '_' . $key,
                        'value' => is_array($value) ? implode(',', $value) : $value,
                    ];

                    if (($parameter = $parameters->firstWhere('key', $data['key'])) !== null) {
                        $parameterService->update($parameter, $data);
                    } else {
                        $parameterService->create($data);
                    }
                }
            }

            return $this->response->withRedirect($this->request->getQueryParam('return', '/cup/parameters'));
        }

        return $this->respondWithTemplate('cup/parameters/index.twig', [
            'timezone' => collect(\DateTimeZone::listIdentifiers())->mapWithKeys(fn ($item) => [$item => $item]),
            'routes' => [
                'all' => $this->getRoutes()->all(),
                'guest' => $this->getRoutes()->filter(fn ($el) => str_start_with($el, ['api:', 'common:']))->all(),
            ],
            'groups' => UserGroupService::getWithContainer($this->container)->read(),
            'attributes' => CatalogAttributeService::getWithContainer($this->container)->read()->whereNotIn('type', \App\Domain\Types\Catalog\AttributeTypeType::TYPE_BOOLEAN),
            'parameter' => $parameters,
        ]);
    }
}
