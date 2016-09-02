<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 29.08.2016
 */
namespace skeeks\cms\importCsvContent;

use skeeks\cms\importCsv\handlers\CsvHandler;
use skeeks\cms\importCsv\helpers\CsvImportRowResult;
use skeeks\cms\importCsv\ImportCsvHandler;
use skeeks\cms\importCsvContent\widgets\MatchingInput;
use skeeks\cms\models\CmsContent;
use skeeks\cms\models\CmsContentElement;
use skeeks\cms\models\CmsContentPropertyEnum;
use skeeks\cms\relatedProperties\PropertyType;
use skeeks\cms\relatedProperties\propertyTypes\PropertyTypeElement;
use skeeks\cms\relatedProperties\propertyTypes\PropertyTypeList;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\widgets\ActiveForm;

/**
 * @property CmsContent $cmsContent
 *
 * Class CsvContentHandler
 *
 * @package skeeks\cms\importCsvContent
 */
class ImportCsvContentHandler extends ImportCsvHandler
{
    public $content_id = null;
    public $unique_field = null;

    public function getAvailableFields()
    {
        $element = new CmsContentElement([
            'content_id' => $this->cmsContent->id
        ]);

        $fields = [];

        foreach ($element->attributeLabels() as $key => $name)
        {
            $fields['element.' . $key] = $name;
        }

        foreach ($element->relatedPropertiesModel->attributeLabels() as $key => $name)
        {
            $fields['property.' . $key] = $name . " [свойство]";
        }

        $fields['image'] = 'Ссылка на главное изображение';

        return array_merge(['' => ' - '], $fields);
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


    /**
     * Соответствие полей
     * @var array
     */
    public $matching = [];

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [

            ['content_id' , 'required'],
            ['content_id' , 'integer'],

            ['unique_field' , 'string'],

            [['matching'], 'safe'],
            [['matching'], function($attribute) {
                if (!in_array('element.name', $this->$attribute))
                {
                    $this->addError($attribute, "Укажите соответствие названия");
                }
            }]
        ]);
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'content_id'        => \Yii::t('skeeks/importCsvContent', 'Контент'),
            'matching'          => \Yii::t('skeeks/importCsvContent', 'Preview content and configuration compliance'),
            'unique_field'      => \Yii::t('skeeks/importCsvContent', 'Уникальная колонка'),
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

        if ($this->content_id && $this->rootFilePath && file_exists($this->rootFilePath))
        {
            echo $form->field($this, 'matching')->widget(
                \skeeks\cms\importCsv\widgets\MatchingInput::className()
            );

            echo $form->field($this, 'unique_field')->listBox(
                array_merge(['' => ' - '], $this->getAvailableFields()), [
                'size' => 1,
            ]);
        }
    }

    protected function _initModelByField(CmsContentElement &$cmsContentElement, $fieldName, $value)
    {
        if (strpos("field_" . $fieldName, 'element.'))
        {
            $realName = str_replace("element.", "", $fieldName);
            $cmsContentElement->{$realName} = $value;

        } else if (strpos("field_" . $fieldName, 'property.'))
        {

            $realName = str_replace("property.", "", $fieldName);
            $property = $cmsContentElement->relatedPropertiesModel->getRelatedProperty($realName);

            if ($property->property_type == PropertyType::CODE_ELEMENT)
            {
                if ($property = $cmsContentElement->relatedPropertiesModel->getRelatedProperty($realName))
                {
                    $content_id = $property->createPropertyType()->content_id;

                    $brand = CmsContentElement::find()
                                ->where(['content_id' => $content_id])
                                ->andWhere(['name' => $value])
                                ->one()
                            ;

                    if (!$brand)
                    {
                        $brand = new CmsContentElement();
                        $brand->name = $value;
                        $brand->content_id = $content_id;
                        $brand->save();
                    }

                    if ($brand && !$brand->isNewRecord)
                    {
                        $cmsContentElement->relatedPropertiesModel->setAttribute($realName, $brand->id);
                    }


                }

            } else if($property->property_type == PropertyType::CODE_LIST)
            {
                if ($property = $cmsContentElement->relatedPropertiesModel->getRelatedProperty($realName))
                {
                    if ( $enum = $property->getEnums()->andWhere(['value' => $value])->one() )
                    {

                    } else
                    {
                        $enum = new CmsContentPropertyEnum();
                        $enum->value        = $value;
                        $enum->property_id  = $property->id;
                        $enum->save();
                    }

                    if ($enum && !$enum->isNewRecord)
                    {
                        $cmsContentElement->relatedPropertiesModel->setAttribute($realName, $enum->id);
                    }
                }
            } else
            {
                $cmsContentElement->relatedPropertiesModel->setAttribute($realName, $value);
            }
        }
    }

    /**
     * @param $code
     *
     * @return int|null
     */
    public function getColumnNumber($code)
    {
        if (in_array($code, $this->matching))
        {
            foreach ($this->matching as $number => $codeValue)
            {
                if ($codeValue == $code)
                {
                    return (int) $number;
                }
            }
        }

        return null;
    }

    /**
     * @param $code
     * @param array $row
     *
     * @return null
     */
    public function getValue($code, $row = [])
    {
        $number = $this->getColumnNumber($code);

        if ($number !== null)
        {
            return $row[$number];
        }

        return null;
    }

    /**
     * @param $number
     * @param $row
     *
     * @return CsvImportRowResult
     */
    public function import($number, $row)
    {
        $result = new CsvImportRowResult();

        try
        {
            $isUpdate = false;
            $element = null;

            if (!$this->unique_field)
            {
                $element                = new CmsContentElement();
                $element->content_id    =   $this->content_id;
            } else
            {
                $uniqueValue = trim($this->getValue($this->unique_field, $row));
                if ($uniqueValue)
                {
                    if (strpos("field_" . $this->unique_field, 'element.'))
                    {
                        $realName = str_replace("element.", "", $this->unique_field);
                        $element = CmsContentElement::find()->where([$realName => $uniqueValue])->one();

                    } else if (strpos("field_" . $this->unique_field, 'property.'))
                    {
                        $realName = str_replace("property.", "", $this->unique_field);

                        $element = CmsContentElement::find()

                            ->joinWith('relatedElementProperties map')
                            ->joinWith('relatedElementProperties.property property')

                            ->andWhere(['property.code'     => $realName])
                            ->andWhere(['map.value'         => $uniqueValue])

                            ->joinWith('cmsContent as ccontent')
                            ->andWhere(['ccontent.id'        => $this->content_id])

                            ->one()
                        ;
                    }
                } else
                {
                    throw new Exception('Не задано уникальное значение');
                }

                if (!$element)
                {
                    $element                = new CmsContentElement();
                    $element->content_id    =   $this->content_id;
                } else
                {
                    $isUpdate = true;
                }
            }

            foreach ($this->matching as $number => $fieldName)
            {
                //Выбрано соответствие
                if ($fieldName)
                {
                    $this->_initModelByField($element, $fieldName, $row[$number]);
                }
            }

            $element->validate();
            $element->relatedPropertiesModel->validate();

            if (!$element->errors && !$element->relatedPropertiesModel->errors)
            {
                $element->save();

                if (!$element->relatedPropertiesModel->save())
                {
                    throw new Exception('Не сохранены дополнительные данные');
                };

                $imageUrl = $this->getValue('image', $row);
                if ($imageUrl && !$element->image)
                {
                    try
                    {
                        $file = \Yii::$app->storage->upload($imageUrl, [
                            'name' => $element->name
                        ]);

                        $element->link('image', $file);

                    } catch (\Exception $e)
                    {
                        //\Yii::error('Not upload image to: ' . $cmsContentElement->id . " ({$realUrl})", 'import');
                    }
                }


                $result->message        =   $isUpdate === true ? "Элемент обновлен" : 'Элемент создан' ;

                $rp = Json::encode($element->relatedPropertiesModel->toArray());
                $rp = '';
                $result->html           =   <<<HTML
Элемент: <a href="{$element->url}" data-pjax="0" target="_blank">{$element->id}</a> $rp
HTML;

            } else
            {
                $result->success        =   false;
                $result->message        =   'Ошибка';
                $result->html           =   Json::encode($element->errors) . "<br />" . Json::encode($element->relatedPropertiesModel->errors);
            }

            $result->data           =   $this->matching;


        } catch (\Exception $e)
        {
            $result->success        =   false;
            $result->message        =   $e->getMessage();
        }






        return $result;
    }

}