<?php declare(strict_types=1);

return [
    // special
    Ramsey\Uuid\Doctrine\UuidType::NAME => Ramsey\Uuid\Doctrine\UuidType::class,

    // custom
    App\Domain\Types\Catalog\AttributeTypeType::NAME => App\Domain\Types\Catalog\AttributeTypeType::class,
    App\Domain\Types\Catalog\CategoryStatusType::NAME => App\Domain\Types\Catalog\CategoryStatusType::class,
    App\Domain\Types\Catalog\ProductStatusType::NAME => App\Domain\Types\Catalog\ProductStatusType::class,
    App\Domain\Types\Catalog\ProductTypeType::NAME => App\Domain\Types\Catalog\ProductTypeType::class,
    App\Domain\Types\FileItemType::NAME => App\Domain\Types\FileItemType::class,
    App\Domain\Types\GuestBookStatusType::NAME => App\Domain\Types\GuestBookStatusType::class,
    App\Domain\Types\PageTypeType::NAME => App\Domain\Types\PageTypeType::class,
    App\Domain\Types\ReferenceTypeType::NAME => App\Domain\Types\ReferenceTypeType::class,
    App\Domain\Types\TaskStatusType::NAME => App\Domain\Types\TaskStatusType::class,
    App\Domain\Types\UserStatusType::NAME => App\Domain\Types\UserStatusType::class,
];
