<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use App\Domain\Entities\User;

class MainPageAction extends AbstractAction
{
    protected function action(): \Slim\Http\Response
    {
        /** @var User $user */
        $user = $this->request->getAttribute('user', false);

        return $this->respondWithTemplate('cup/layout.twig', [
            'notepad' => $this->parameter('notepad_' . $user->getUsername(), ''),
            'stats' => [
                'pages' => $this->entityManager->getRepository(\App\Domain\Entities\Page::class)->count([]),
                'users' => $this->entityManager->getRepository(\App\Domain\Entities\User::class)->count([]),
                'publications' => $this->entityManager->getRepository(\App\Domain\Entities\Publication::class)->count([]),
                'guestbook' => $this->entityManager->getRepository(\App\Domain\Entities\GuestBook::class)->count([]),
                'catalog' => [
                    'category' => $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Category::class)->count(['status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK]),
                    'product' => $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Product::class)->count(['status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK]),
                    'order' => $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Order::class)->count([]),
                ],
                'files' => $this->entityManager->getRepository(\App\Domain\Entities\File::class)->count([]),
            ],
            'properties' => [
                'version' => [
                    'branch' => ($_ENV['COMMIT_BRANCH'] ?? 'other'),
                    'commit' => ($_ENV['COMMIT_SHA'] ?? 'specific'),
                ],
                'whois' => $this->whois(),
                'os' => @implode(' ', [php_uname('s'), php_uname('r'), php_uname('m')]),
                'php' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'disable_functions' => ini_get('disable_functions'),
                'disable_classes' => ini_get('disable_classes'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'max_file_uploads' => ini_get('max_file_uploads'),
            ],
        ]);
    }

    // generate and cache whois data
    protected function whois()
    {
        \RunTracy\Helpers\Profiler\Profiler::start('whois');

        $result = [];
        $paramService = \App\Domain\Service\Parameter\ParameterService::getWithContainer($this->container);
        $domain = $paramService->read(['key' => 'common_homepage'])->getValue();

        if (mb_substr_count($domain, '.') === 1) {
            $whois = $paramService->read(['key' => 'common_whois'], 'a:0:{}');
            $whoisValue = unserialize($whois->getValue());

            if (
                !$whoisValue ||
                $whoisValue['update']->diff(new \DateTime())->d >= 1 ||
                mb_strpos($domain, $whoisValue['result']['domain']) === false
            ) {
                $domain = str_replace(['https', 'http', '://', '/'], '', $domain);

                $defaults = [
                    'update' => new \DateTime(),
                    'result' => [],
                ];
                $whoisValue = array_merge($defaults, $whoisValue);
                $whoisValue['result'] = \App\Application\Whois::query($domain);
                $paramService->update($whois, ['key' => 'common_whois', 'value' => serialize($whoisValue)]);
            }

            $result = $whoisValue['result'];
        }

        \RunTracy\Helpers\Profiler\Profiler::finish('whois');
        
        return $result;
    }
}
