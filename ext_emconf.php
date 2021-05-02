<?php

$EM_CONF['form_multi_file_upload'] = [
    'title' => 'Multi file uploads for forms',
    'description' => 'Proof of concept for adding a multi file upload to TYPO3',
    'category' => 'example',
    'author' => 'Tim Schreiner',
    'author_email' => 'dev@tim-schreiner.de',
    'state' => 'test',
    'clearCacheOnLoad' => true,
    'version' => '0.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '10.4.0-10.4.99',
            'form' => '10.4.0-10.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
