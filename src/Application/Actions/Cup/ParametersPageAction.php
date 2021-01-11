<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use App\Domain\Service\Parameter\ParameterService;

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
            'routes' => [
                'all' => $this->getRoutes()->all(),
                'guest' => $this->getRoutes()->filter(fn ($el) => str_start_with($el, ['api:', 'common:']))->all(),
            ],
            'parameter' => $parameters,
        ]);
    }
}
