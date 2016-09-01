<?php
return [

    'components' =>
    [
        'cmsImport' => [
            'handlers'     =>
            [
                'skeeks\cms\importCsvContent\ImportCsvContentHandler' =>
                [
                    'class' => 'skeeks\cms\importCsvContent\ImportCsvContentHandler'
                ]
            ]
        ],

        'i18n' => [
            'translations' =>
            [
                'skeeks/importCsvContent' => [
                    'class'             => 'yii\i18n\PhpMessageSource',
                    'basePath'          => '@skeeks/cms/importCsvContent/messages',
                    'fileMap' => [
                        'skeeks/importCsvContent' => 'main.php',
                    ],
                ]
            ]
        ]
    ]
];