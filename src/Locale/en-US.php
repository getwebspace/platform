<?php declare(strict_types=1);

return [
    // ***
    // Common | other
    // ***

    // status
    'work' => 'Work',
    'delete' => 'Delete',
    'moderate' => 'Moderate',
    'block' => 'Block',
    'cancel' => 'Canceled',

    // attributes
    'String' => 'string',
    'Integer' => 'integer',
    'Float' => 'float',
    'Boolean' => 'boolean',

    // product type
    'product' => 'Product',
    'service' => 'Service',

    // api access
    'key' => 'Only keys',
    'user' => 'Users and Keys',

    // user auth by
    'username' => 'Login',
    'email' => 'E-Mail',
    'phone' => 'Telephone',

    // user email list mode
    'blacklist' => 'Blacklist',
    'whitelist' => 'Whitelist',

    // user without group
    'WITHOUT_GROUP' => 'Without group',

    // newsletters
    'all' => 'To all',
    'users' => 'Members',
    'subscribers' => 'Subscribers',

    // boolean
    'yes' => 'Yes',
    'no' => 'No',
    'off' => 'Off',
    'on' => 'On',

    // sorts by
    'title' => 'Title',
    'price' => 'Price',
    'price_wholesale' => 'Price wholesale',
    'stock' => 'Stock',
    'date' => 'Date',
    'DESC' => 'Desc',
    'ASC' => 'Asc',

    // content type
    'html' => 'HTML',
    'text' => 'Text',

    // system install
    'system_demo' => 'Demo + Default data',
    'system_default' => 'Default data',

    // ***
    // Exceptions
    // ***

    // exists
    'EXCEPTION_TITLE_ALREADY_EXISTS' => 'Title already exists',
    'EXCEPTION_ADDRESS_ALREADY_EXISTS' => 'Address already exists',
    'EXCEPTION_FILE_ALREADY_EXISTS' => 'File already exists',
    'EXCEPTION_PARAMETER_ALREADY_EXISTS' => 'Parameter already exists',
    'EXCEPTION_EMAIL_ALREADY_EXISTS' => 'E-Mail already exists',
    'EXCEPTION_PHONE_ALREADY_EXISTS' => 'Phone already exists',
    'EXCEPTION_USERNAME_ALREADY_EXISTS' => 'Username already exists',

    // missing
    'EXCEPTION_TITLE_MISSING' => 'Title missing',
    'EXCEPTION_EMAIL_MISSING' => 'E-Mail missing',
    'EXCEPTION_MESSAGE_MISSING' => 'Message missing',
    'EXCEPTION_NAME_MISSING' => 'Name missing',
    'EXCEPTION_USER_UUID_MISSING' => 'User UUID missing',
    'EXCEPTION_ACTION_VALUE_MISSING' => 'Action value missing',
    'EXCEPTION_UNIQUE_MISSING' => 'Unique value missing',

    // not found
    'EXCEPTION_ATTRIBUTE_NOT_FOUND' => 'Attribute not found',
    'EXCEPTION_CATEGORY_NOT_FOUND' => 'Category not found',
    'EXCEPTION_ORDER_NOT_FOUND' => 'Order not found',
    'EXCEPTION_ORDER_STATUS_NOT_FOUND' => 'Order status not found',
    'EXCEPTION_PRODUCT_NOT_FOUND' => 'Product not found',
    'EXCEPTION_RELATION_NOT_FOUND' => 'Relation not found',
    'EXCEPTION_FILE_NOT_FOUND' => 'File not found',
    'EXCEPTION_FORM_NOT_FOUND' => 'Form not found',
    'EXCEPTION_FORM_DATA_NOT_FOUND' => 'Form data not found',
    'EXCEPTION_ENTRY_NOT_FOUND' => 'Entry not found',
    'EXCEPTION_PAGE_NOT_FOUND' => 'Page not found',
    'EXCEPTION_PARAMETER_NOT_FOUND' => 'Parameter not found',
    'EXCEPTION_PUBLICATION_NOT_FOUND' => 'Publication not found',
    'EXCEPTION_TASK_NOT_FOUND' => 'Task not found',
    'EXCEPTION_USER_NOT_FOUND' => 'User not found',
    'EXCEPTION_USER_INTEGRATION_NOT_FOUND' => 'Integration not found',
    'EXCEPTION_USER_TOKEN_NOT_FOUND' => 'Token not found',
    'EXCEPTION_USER_GROUP_NOT_FOUND' => 'User group not found',

    // other
    'EXCEPTION_EMAIL_BANNED' => 'This domain cannot be used',
    'EXCEPTION_WRONG_USERNAME' => 'Wrong username',
    'EXCEPTION_WRONG_NAME' => 'Wrong name',
    'EXCEPTION_WRONG_TITLE' => 'Wrong title',
    'EXCEPTION_WRONG_EMAIL' => 'Wrong format E-Mail',
    'EXCEPTION_WRONG_PHONE' => 'Wrong format phone',
    'EXCEPTION_WRONG_IP' => 'Wrong format IP address',
    'EXCEPTION_WRONG_CODE' => 'Incorrect code',
    'EXCEPTION_WRONG_CODE_TIMEOUT' => 'You can update the authorization code every 10 minutes',
    'EXCEPTION_WRONG_PASSWORD' => 'Wrong password',
    'EXCEPTION_WRONG_GRECAPTCHA' => 'Error validating Google Recaptcha token',
];
