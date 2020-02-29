<?php

namespace App\Domain\Tasks\Dev;

use App\Domain\Tasks\Task;

class MigrateFileClaimTask extends Task
{
    public function execute(array $params = []): \App\Domain\Entities\Task
    {
        $default = [
            // nothing
        ];
        $params = array_merge($default, $params);

        return parent::execute($params);
    }

    protected function action(array $args = [])
    {
        $rsm = new \Doctrine\ORM\Query\ResultSetMapping();

        // catalog_category_files
        $this->entityManager->createNativeQuery("
            insert into catalog_category_files(category_uuid, file_uuid)
            select item_uuid, uuid
            from file
            where `item` = 'catalog_category' and item_uuid is not null;
        ", $rsm)->execute();

        // catalog_product_files
        $this->entityManager->createNativeQuery("
            insert into catalog_product_files(product_uuid, file_uuid)
            select item_uuid, uuid
            from file
            where `item` = 'catalog_product' and item_uuid is not null;
        ", $rsm)->execute();

        // form_data_files
        $this->entityManager->createNativeQuery("
            insert into form_data_files(data_uuid, file_uuid)
            select item_uuid, uuid
            from file
            where `item` = 'form_data' and item_uuid is not null;
        ", $rsm)->execute();

        // page_files
        $this->entityManager->createNativeQuery("
            insert into page_files(page_uuid, file_uuid)
            select item_uuid, uuid
            from file
            where `item` = 'page' and item_uuid is not null;
        ", $rsm)->execute();

        // publication_files
        $this->entityManager->createNativeQuery("
            insert into publication_files(publication_uuid, file_uuid)
            select item_uuid, uuid
            from file
            where `item` = 'publication' and item_uuid is not null;
        ", $rsm)->execute();

        // user_files
        $this->entityManager->createNativeQuery("
            insert into user_files(user_uuid, file_uuid)
            select item_uuid, uuid
            from file
            where `item` = 'user_upload' and item_uuid is not null;
        ", $rsm)->execute();

        $this->setStatusDone();
    }
}
