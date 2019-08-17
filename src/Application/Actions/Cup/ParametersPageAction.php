<?php

namespace Application\Actions\Cup;

use Application\Actions\Action;
use DateTime;
use Exception;
use Psr\Container\ContainerInterface;

class ParametersPageAction extends Action
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $models = $this->getParameter();

            foreach ($this->request->getParsedBody() as $group => $params) {
                foreach ($params as $key => $value) {
                    $data = [
                        'key' => $group . '_' . $key,
                        'value' => $value,
                    ];

                    $check = \Domain\Filters\Parameter::check($data);

                    if ($check === true) {
                        $model = $models->firstWhere('key', $data['key']) ?? new \Domain\Entities\Parameter();
                        $model->replace($data);
                        $this->entityManager->persist($model);
                    } else {
                        \AEngine\Support\Form::$globalError[$group . '[' . $key . ']'] = \Domain\References\Errors\Parameter::WRONG_VALUE;
                    }
                }
            }

            $this->entityManager->flush();

            return $this->response->withAddedHeader('Location', $this->request->getQueryParam('return', '/cup/parameters'));
        }

        return $this->respondRender('cup/parameters/index.twig', ['parameter' => $this->getParameter()]);
    }
}
