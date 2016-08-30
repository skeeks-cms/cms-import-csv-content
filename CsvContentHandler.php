<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 29.08.2016
 */
namespace skeeks\cms\importCsvContent;

use skeeks\cms\importCsv\handlers\CsvHandler;
use skeeks\cms\importCsvContent\widgets\MatchingInput;
use skeeks\cms\models\CmsContent;
use skeeks\cms\models\CmsContentElement;
use yii\helpers\ArrayHelper;
use yii\widgets\ActiveForm;

/**
 * Class CsvContentHandler
 *
 * @package skeeks\cms\importCsvContent
 */
class CsvContentHandler extends CsvHandler
{
    public $matching = [];
    public $content_id = null;

    public function init()
    {
        parent::init();

        $this->name = \Yii::t('skeeks/importCsvContent', 'Import CSV content items');
    }


    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
            ['matching' , 'required'],
            ['matching' , 'safe'],

            ['content_id' , 'required'],
            ['content_id' , 'integer'],
        ]);
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'content_id'        => \Yii::t('skeeks/importCsvContent', 'Контент'),
            'matching'          => \Yii::t('skeeks/importCsvContent', 'Соотвествие'),
        ]);
    }

    /**
     * @return array
     */
    public function getCsvColumns()
    {
        $handle = fopen($this->taskModel->rootFilePath, "r");

        while (($data = fgetcsv($handle, 0, $this->csvDelimetr)) !== FALSE)
        {
            return $data;
        }

        return [];
    }

    /**
     * @param ActiveForm $form
     */
    public function renderConfigForm(ActiveForm $form)
    {
        parent::renderConfigForm($form);

        echo $form->field($this, 'content_id')->listBox(array_merge(['' => ' - '], CmsContent::getDataForSelect()), ['size' => 1, 'id' => 'sx-select-content']);

        if ($this->content_id)
        {
            echo $form->field($this, 'matching')->widget(
                MatchingInput::className()
            );
        }

        \Yii::$app->view->registerJs(<<<JS
        $("#sx-select-content").on('change', function()
        {
            sx.CsvImport.update();
            return false;
        });
JS
);
    }

}