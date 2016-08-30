<?php
return [

    'components' =>
    [
        'importCsv' => [
            'handlers'     =>
            [
                'skeeks\cms\importCsvContent\CsvContentHandler' =>
                [
                    'class' => 'skeeks\cms\importCsvContent\CsvContentHandler'
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