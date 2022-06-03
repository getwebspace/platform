<?php declare(strict_types=1);

return [
    // ***
    // Publication
    // ***

    // list
    'Список публикаций' => 'List of publications',
    'Создать публикацию' => 'Create publication',
    'Автор' => 'Author',
    'Вы действительно хотите удалить публикацию?' => 'Are you sure you want to delete the publication?',

    // form
    'Создание новой публикации' => 'Create a new publication',
    'Редактирование публикации' => 'Publication editing',
    'Публикация' => 'Publication',
    'Опрос' => 'Poll',
    'Предварительный просмотр' => 'Preview',
    'Заголовок публикации обязателен к заполнению и может содержать не более 255 символов' => 'The title of the publication is required and can contain no more than 255 characters',
    'Cсылка для просмотра статьи в браузере. Можно оставить пустым, тогда значение будет сгенерировано автоматически. Допустимо использование только латинских символов и цифер без пробелов' => 'Link to view the article. You can leave it blank, then the value will be generated automatically. Only Latin characters and numbers without spaces are allowed',
    'Краткое содержимое' => 'Brief content',
    'Полное содержимое' => 'Full content',

    // ***
    // Publication category
    // ***

    // list
    'Список категорий' => 'List of categories',
    'Публичная' => 'Is public',
    'Вложенные' => 'With nested',
    'Вы действительно хотите удалить категорию публикаций?' => 'Are you sure you want to delete a post category?',

    // form
    'Новости' => 'News',
    'Название новой категории' => 'Name of the new category',
    'Используется для просмотра всех статей в данной категории. Можно оставить пустым, тогда значение будет сгенерировано автоматически. Допустимо использование только латинских символов и цифер без пробелов' => 'Used to view all articles in a given category. You can leave it blank, then the value will be generated automatically. It is allowed to use only Latin characters and numbers without spaces',
    'Количество записей на страницу' => 'Number of entries per page',
    'Публичная категория' => 'Public category',
    'Отображать ли категорию в общем списке' => 'Whether to show the category in the general list',
    'Шаблон краткой версии' => 'Short version template',
    'Шаблон полной версии' => 'Full version template',
    'Вы можете установить для данной категории отдельный шаблон или оставить <b>publication.list.twig</b>' => 'You can set a separate template for this category or leave <b>publication.list.twig</b>',
    'Укажите название шаблона, который хотите использовать для отображения краткой версии публикации или оставьте <b>publication.short.twig</b>' => 'Specify the name of the template you want to use to display the short version of the publication, or leave <b>publication.short.twig</b>',
    'Укажите название шаблона, который хотите использовать для отображения полной версии публикации или оставьте <b>publication.full.twig</b>' => 'Specify the name of the template you want to use to display the full version of the publication, or leave <b>publication.full.twig</b>',
];
