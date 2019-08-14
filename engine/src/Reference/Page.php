<?php

namespace Reference;

class Page
{
    // possible page types
    const TYPE_HTML = 'html',
          TYPE_TEXT = 'text';

    // list of types
    const TYPE = [
        self::TYPE_HTML  => 'Исходный текст HTML',
        self::TYPE_TEXT  => 'Простой текст',
    ];
}
