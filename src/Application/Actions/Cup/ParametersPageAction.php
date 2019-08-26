<?php

namespace App\Application\Actions\Cup;

use App\Application\Actions\Action;

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

                    $check = \App\Domain\Filters\Parameter::check($data);

                    if ($check === true) {
                        $model = $models->firstWhere('key', $data['key']) ?? new \App\Domain\Entities\Parameter();
                        $model->replace($data);
                        $this->entityManager->persist($model);
                    } else {
                        \AEngine\Support\Form::$globalError[$group . '[' . $key . ']'] = \App\Domain\References\Errors\Parameter::WRONG_VALUE;
                    }
                }
            }

            $this->entityManager->flush();

            return $this->response->withAddedHeader('Location', $this->request->getQueryParam('return', '/cup/parameters'));
        }

        return $this->respondRender('cup/parameters/index.twig', ['parameter' => $this->getParameter()]);
    }
}
