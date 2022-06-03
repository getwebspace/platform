<?php declare(strict_types=1);

return [
    // ***
    // Catalog
    // ***

    // system install
    'Новый' => 'New',
    'В обработке' => 'Processing',
    'Отправлен' => 'Sent',
    'Доставлен' => 'Delivered',
    'Отменён' => 'Canceled',
    'Килограмм' => 'Kilogram', 'кг' => 'kg',
    'Грамм' => 'Gram', 'г' => 'g',
    'Литр' => 'Liter', 'л' => 'l',
    'Миллилитр' => 'Milliliter', 'мл' => 'ml',

    // attributes
    'string' => 'String',
    'integer' => 'Integer',
    'float' => 'Float',
    'boolean' => 'Boolean',

    // product type
    'product' => 'Product',
    'service' => 'Service',

    // attributes page
    'Атрибуты' => 'Attributes',
    'Список атрибутов' => 'List of attributes',
    'Создать атрибут' => 'Create attribute',
    'Добавить аттрибут' => 'Add attribute',
    'Создание нового атрибута' => 'Creating a new attribute',
    'Редактирование атрибута' => 'Editing an Attribute',
    'Вы действительно хотите удалить аттрибут?' => 'Are you sure you want to delete the attribute?',
    '<b>Внимание</b>: тип "Булево" может быть добавлен только к категории.' => '<b>Warning</b>: The Boolean type can only be added to a category.',
    'Название атрибута. Можно оставить пустым, тогда значение будет сгенерировано автоматически. Допустимо использование только латинских символов и цифер без пробелов' => 'Attribute name. You can leave it blank, then the value will be generated automatically. Only Latin characters and numbers without spaces are allowed',

    // category
    'Краткое описание категории' => 'Brief description of the category',
    'Вы действительно хотите удалить категорию?' => 'Are you sure you want to delete a category?',
    'Описание категории' => 'Category description',
    'Количество товаров на страницу' => 'Number of products per page',
    'Укажите индивидуальные атрибуты для категории' => 'Specify individual attributes for the category',
    'Поля' => 'Fields',
    'Поле' => 'Field',
    'Индивидуальное поле' => 'Individual field',
    'Название поля' => 'Field name',
    'Индивидуальное поле продукта' => 'Customized product field',
    'Порядок' => 'Order',
    'Шаблон товара' => 'Category product',
    'Название категории. Можно оставить пустым, тогда значение будет сгенерировано автоматически. Допустимо использование только латинских символов и цифер без пробелов' => 'Name of category. You can leave it blank, then the value will be generated automatically. It is allowed to use only Latin characters and numbers without spaces',
    'Укажите название шаблона, который хотите использовать для данной категории или оставьте <b>catalog.category.twig</b>' => 'Specify the name of the template you want to use for this category or leave <b>catalog.category.twig</b>',
    'Укажите название шаблона, который хотите использовать для товаров в данной категории или оставьте <b>catalog.product.twig</b>' => 'Specify the name of the template you want to use for products in this category or leave <b>catalog.product.twig</b>',

    // product
    'Импорт продуктов' => 'Import products',
    'Экспорт текущего списка продуктов' => 'Export current product list',
    'Создать продукт' => 'Create product',
    'Объем упаковки' => 'Packing volume',
    'Вы действительно хотите удалить продукт?' => 'Are you sure you want to uninstall the product?',
    'Создание нового продукта' => '',
    'Редактирование продукта' => '',
    'Продукт' => 'Product',
    'Сопутствующие' => 'Related',
    'Краткое описание товара' => 'Brief product description',
    'Еда' => 'Food',
    'Описание товара' => 'Description',
    'Страна' => 'Country',
    'Производитель' => 'Manufacturer',
    'Артикул' => 'Vendor code',
    'Штрих код' => 'Barcode',
    'Цена закупки' => 'First price',
    'Цена продажи' => 'Price',
    'Цена оптовая' => 'Price wholesale',
    'Налог' => 'Tax',
    'Акция' => 'Special',
    'Ширина (см)' => 'Width (cm)',
    'Высота (см)' => 'Height (cm)',
    'Длинна (см)' => 'Length (cm)',
    'Объем' => 'Volume',
    'Зависит от выбранной размерности' => 'Depends on the chosen dimension',
    'Размерность' => 'Dimension',
    'На складе' => 'In stock',
    '<b>Сопутствующие товары</b> - это те товары, которые покупатель использует вместе с уже купленным товаром, которые помогают им пользоваться, дополняют его, устраняют последствия от использования товара, являются его сменными деталями, расходными материалами и т.п.' => '<b>Related products</b> are those products that the buyer uses together with already purchased goods that help them use, complement it, eliminate the consequences of using goods, are its replaceable parts, consumables, etc.',
    'Добавить продукт' => 'Add product',
    'Выберете категорию' => 'Choose a category',
    'Выберете товар' => 'Choose a product',
    'Атрибут категории' => 'Attribute from category',
    'Да' => 'Yes',
    'Укажите индивидуальные атрибуты товара' => 'Specify individual attributes of the product',
    'Название товара. Можно оставить пустым, тогда значение будет сгенерировано автоматически. Допустимо использование только латинских символов и цифер без пробелов' => 'Product Name. You can leave it blank, then the value will be generated automatically. It is allowed to use only Latin characters and numbers without spaces',
    'Дополнительное описание товара' => 'Additional description',

    // order
    'Шаблон инвойса' => 'Invoice template',
    'Статусы заказа' => 'Order statuses',
    'Список заказов' => 'List of orders',
    'Создать заказ' => 'Create order',
    'Клиент' => 'Client',
    'Доставка и статус' => 'Delivery and status',
    'Вы действительно хотите удалить заказ?' => 'Are you sure you want to delete the order?',
    'Создание нового заказа' => 'Create a new order',
    'Редактирование заказа' => 'Edit order',
    'ФИО клиента' => 'Client name',
    'Адрес доставки' => 'Delivery address',
    'Дата доставки' => 'Delivery date',
    'Техническая информация' => 'Technical information',
    'Состав заказа' => 'Order list',

    // order status
    'Создать статус' => 'Create status',
    'Вы действительно хотите удалить статус заказа?' => 'Are you sure you want to delete the order status?',
    'Создание нового статуса заказа' => 'Create a new order status',
    'Редактирование статуса заказа' => 'Edit order status',

    // order invoice
    'Инвойс' => 'Invoice',
    'Заказ' => 'Order',
    'Изменить заказ' => 'Edit order',
    'Доставка' => 'Delivery',
    'Позиция' => 'Item',
    'Цена' => 'Price',
    'Количество' => 'Quantity',
    'Сумма' => 'Sum',
    'Общая сумма' => 'Total price',
];
