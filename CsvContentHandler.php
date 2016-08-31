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
 * @property CmsContent $cmsContent
 *
 * Class CsvContentHandler
 *
 * @package skeeks\cms\importCsvContent
 */
class CsvContentHandler extends CsvHandler
{
    public $content_id = null;
    
    public function getAvailableFields()
    {
        $element = new CmsContentElement();
        return array_merge(['' => ' - '], $element->attributeLabels());
    }

    /**
     * @return null|CmsContent
     */
    public function getCmsContent()
    {
        if (!$this->content_id)
        {
            return null;
        }

        return CmsContent::findOne($this->content_id);
    }

    public function init()
    {
        parent::init();

        $this->name = \Yii::t('skeeks/importCsvContent', 'Import CSV content items');
    }


    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [

            ['content_id' , 'required'],
            ['content_id' , 'integer'],
        ]);
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'content_id'        => \Yii::t('skeeks/importCsvContent', 'Контент'),
        ]);
    }

    /**
     * @param ActiveForm $form
     */
    public function renderConfigForm(ActiveForm $form)
    {
        parent::renderConfigForm($form);

        echo $form->field($this, 'content_id')->listBox(
            array_merge(['' => ' - '], CmsContent::getDataForSelect()), [
            'size' => 1,
            'data-form-reload' => 'true'
        ]);

        if ($this->content_id)
        {
            echo $form->field($this, 'matching')->widget(
                \skeeks\cms\importCsv\widgets\MatchingInput::className()
            );
        }

    }

}