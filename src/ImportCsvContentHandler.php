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
use skeeks\cms\models\CmsContentElementImage;
use skeeks\cms\models\CmsContentElementProperty;
use skeeks\cms\models\CmsContentProperty;
use skeeks\cms\models\CmsContentPropertyEnum;
use skeeks\cms\relatedProperties\PropertyType;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\httpclient\Client;
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
    public $new_elements_is_active = false;
    public $titles_row_number = false;


    /*public function getAttributeValueCallbacs()
    {

        return [
            'trim' => 'Удалить пробелы по краям',
            'trim' => 'Удалить пробелы по краям',
        ];
    }*/

    public function getUnique_field()
    {
        if (!$this->matching) {
            return null;
        }

        foreach ((array)$this->matching as $key => $columnSetting) {
            if (is_array($columnSetting)) {
                if (isset($columnSetting['unique']) && $columnSetting['unique']) {
                    return $columnSetting['code'];
                }
            }
        }

        return null;
    }

    public function getAvailableFields()
    {
        $element = new CmsContentElement([
            'content_id' => $this->cmsContent->id,
        ]);

        $fields = [];
        $fields['element.external_id'] = "Уникальный код";
        $fields['element.active'] = "Активность";

        $fields['element.name'] = "Название";
        $fields['element.description_short'] = "Короткое описание";
        $fields['element.description_full'] = "Подробное описание";

        $fields['element.tree_id'] = "ID главного раздела";

        $fields['element.meta_title'] = "Мета заголовок";
        $fields['element.meta_description'] = "Мета description";
        $fields['element.meta_keywords'] = "Мета ключевые слова";

        $fields['image'] = 'Ссылка на главное изображение';
        //$fields['main_image_images'] = 'Ссылка на главное изображение и второстепенные';
        //$fields['images'] = 'Ссылки на второстепенные изображения';

        $q = $this->cmsContent->getCmsContentProperties();
        $q->andWhere([
            'or',
            [CmsContentProperty::tableName().'.cms_site_id' => null],
            [CmsContentProperty::tableName().'.cms_site_id' => \Yii::$app->skeeks->site->id],
        ]);
        $q->orderBy(['priority' => SORT_ASC]);

        if ($properties = $q->all()) {
            /**
             * @var $property CmsContentProperty
             */
            foreach ($properties as $property)
            {
                $fields['property.'.$property->code] = $property->name." [свойство][".$property->code."]";
            }
        }

        /*$element->relatedPropertiesModel->initAllProperties();
        foreach ($element->relatedPropertiesModel->attributeLabels() as $key => $name) {
            $p = $element->relatedPropertiesModel->getRelatedProperty($key);
            $fields['property.'.$key] = $name." [свойство][".$p->code."]";
        }*/


        return array_merge(['' => ' - '], $fields);
    }

    /**
     * @return null|CmsContent
     */
    public function getCmsContent()
    {
        if (!$this->content_id) {
            return null;
        }

        return CmsContent::findOne($this->content_id);
    }

    public function init()
    {
        parent::init();

        $this->name = \Yii::t('skeeks/importCsvContent', '[CSV] Import content items');
    }


    /**
     * Соответствие полей
     * @var array
     */
    public $matching = [];

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [

            ['content_id', 'required'],
            ['content_id', 'integer'],

            ['new_elements_is_active', 'boolean'],
            ['titles_row_number', 'integer'],

            [['matching'], 'safe'],
            [
                ['matching'],
                function ($attribute) {
                    /*if (!in_array('element.name', $this->$attribute))
                    {
                        $this->addError($attribute, "Укажите соответствие названия");
                    }*/
                },
            ],
        ]);
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'content_id' => \Yii::t('skeeks/importCsvContent', 'Контент'),
            'new_elements_is_active' => \Yii::t('skeeks/importCsvContent', 'Созданные товары будут активны?'),
            'matching'   => \Yii::t('skeeks/importCsvContent', 'Preview content and configuration compliance'),
            'titles_row_number'   => \Yii::t('skeeks/importCsvContent', 'Номер строки которая содержит заголовки'),
        ]);
    }
    public function attributeHints()
    {
        return ArrayHelper::merge(parent::attributeHints(), [
            'new_elements_is_active' => \Yii::t('skeeks/importCsvContent', 'Если выбрано нет, то новосозданные товары будут деактивированы, и вы сможете активировать их для показа на сайте после ручной проверки.'),
        ]);
    }

    /**
     * @param ActiveForm $form
     */
    public function renderConfigForm(ActiveForm $form)
    {
        parent::renderConfigForm($form);

        echo $form->field($this, 'titles_row_number');
        echo $form->field($this, 'new_elements_is_active')->listBox(\Yii::$app->formatter->booleanFormat);
        echo $form->field($this, 'content_id')->listBox(
            array_merge(['' => ' - '], CmsContent::getDataForSelect()), [
            'size'             => 1,
            'data-form-reload' => 'true',
        ]);

        if ($this->content_id && $this->rootFilePath && file_exists($this->rootFilePath)) {
            echo $form->field($this, 'matching')->widget(
                \skeeks\cms\importCsv\widgets\MatchingInput::className(),
                [
                    'columns' => $this->getAvailableFields(),
                ]
            );

            /*echo $form->field($this, 'unique_field')->listBox(
                array_merge(['' => ' - '], $this->getAvailableFields()), [
                'size' => 1,
            ]);*/
        }
    }

    protected function _initModelByField(CmsContentElement &$cmsContentElement, $fieldName, $value)
    {
        if (strpos("field_".$fieldName, 'element.')) {
            $realName = str_replace("element.", "", $fieldName);
            $cmsContentElement->{$realName} = $value;

        } else if (strpos("field_".$fieldName, 'property.')) {

            $realName = str_replace("property.", "", $fieldName);
            $property = $cmsContentElement->relatedPropertiesModel->getRelatedProperty($realName);
            $brands = [];
            $brand = '';

            if ($property->property_type == PropertyType::CODE_ELEMENT) {
                if ($property = $cmsContentElement->relatedPropertiesModel->getRelatedProperty($realName)) {
                    $content_id = $property->handler->content_id;
                    $valueList = explode(',', $value);

                    if (count($valueList) > 1) {
                        foreach ($valueList as $val) {
                            $val = trim($val);
                            if (!$val) {
                                continue;
                            }

                            $brand = CmsContentElement::find()
                                ->where(['content_id' => $content_id])
                                ->andWhere(['name' => $val])
                                ->one();

                            if (!$brand) {
                                $brand = new CmsContentElement();
                                $brand->name = $val;
                                $brand->content_id = $content_id;
                                $brand->save();
                            }
                            $brands[] = $brand->id;
                        }
                        if ($brands) {
                            $cmsContentElement->relatedPropertiesModel->setAttribute($realName, $brands);
                        }
                    } else {
                        $brand = CmsContentElement::find()
                            ->where(['content_id' => $content_id])
                            ->andWhere(['name' => $value])
                            ->one();

                        if (!$brand) {
                            $brand = new CmsContentElement();
                            $brand->name = $value;
                            $brand->content_id = $content_id;
                            $brand->save();
                        }

                        if ($brand && !$brand->isNewRecord) {
                            $cmsContentElement->relatedPropertiesModel->setAttribute($realName, $brand->id);
                        }
                    }


                }

            } else if ($property->property_type == PropertyType::CODE_LIST) {
                if ($property = $cmsContentElement->relatedPropertiesModel->getRelatedProperty($realName)) {
                    $valueList = explode(',', $value);

                    if (count($valueList) > 1) {
                        $enums = [];
                        foreach ($valueList as $val) {
                            $val = trim($val);
                            if (!$val) {
                                continue;
                            }

                            if ($enum = $property->getEnums()->andWhere(['value' => $val])->one()) {

                            } else {
                                $enum = new CmsContentPropertyEnum();
                                $enum->value = $val;
                                $enum->property_id = $property->id;
                                $enum->save();
                            }

                            $enums[] = $enum->id;

                        }
                        if ($enums) {
                            $cmsContentElement->relatedPropertiesModel->setAttribute($realName, $enums);
                        }
                    } else {
                        if ($enum = $property->getEnums()->andWhere(['value' => $value])->one()) {

                        } else {
                            $enum = new CmsContentPropertyEnum();
                            $enum->value = $value;
                            $enum->property_id = $property->id;
                            $enum->save();
                        }

                        if ($enum && !$enum->isNewRecord) {
                            $cmsContentElement->relatedPropertiesModel->setAttribute($realName, $enum->id);
                        }
                    }
                }
            } else {
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
        //if (in_array($code, $this->matching))
        //{
        foreach ($this->matching as $number => $codeValue) {
            if (is_array($codeValue)) {
                $codeValue = $codeValue['code'];
            }

            if ($codeValue == $code) {
                return (int)$number;
            }
        }
        //}

        return null;
    }

    /**
     * @param       $code
     * @param array $row
     *
     * @return null
     */
    public function getValue($code, $row = [])
    {
        $number = $this->getColumnNumber($code);

        if ($number !== null) {
            /*$model = new DynamicModel();
            $model->defineAttribute('value', $row[$number]);
            $model->addRule(['value'], '');*/
            return $row[$number];
        }

        return null;
    }

    /**
     * @param      $fieldName
     * @param      $uniqueValue
     * @param null $contentId
     *
     * @return array|null|\yii\db\ActiveRecord
     */
    public function getElement($fieldName, $uniqueValue, $contentId = null)
    {
        $element = null;

        if (!$contentId) {
            $contentId = $this->content_id;
        }

        if (strpos("field_".$fieldName, 'element.')) {
            $realName = str_replace("element.", "", $fieldName);
            $element = CmsContentElement::find()->where([$realName => $uniqueValue])->one();

        } else if (strpos("field_".$fieldName, 'property.')) {
            $realName = str_replace("property.", "", $fieldName);

            /**
             * @var $property CmsContentProperty
             */
            $property = CmsContentProperty::find()->where(['code' => $realName])->one();
            $query = CmsContentElement::find();
            CmsContentElement::filterByProperty($query, $property, $uniqueValue);

            //print_r($query->createCommand()->rawSql);die;
            $element = $query->one();
        }

        return $element;
    }


    /**
     * Инициализация элемента по строке из файла импорта
     * Происходит его создание поиск по базе
     *
     * @param      $number
     * @param      $row
     * @param null $contentId
     * @param null $className
     *
     * @return CmsContentElement
     *
     * @throws Exception
     */
    protected function _initElement($number, $row, $contentId = null, $className = null)
    {
        if (!$contentId) {
            $contentId = $this->content_id;
        }

        if (!$className) {
            $className = CmsContentElement::className();
        }

        if (!$this->unique_field) {
            $element = new $className();
            $element->content_id = $contentId;
        } else {
            $uniqueValue = trim($this->getValue($this->unique_field, $row));

            if ($uniqueValue) {
                if (strpos("field_".$this->unique_field, 'element.')) {
                    $realName = str_replace("element.", "", $this->unique_field);
                    $element = CmsContentElement::find()
                        ->where([$realName => $uniqueValue])
                        ->andWhere(["cms_site_id" => \Yii::$app->skeeks->site->id])
                        ->andWhere(["content_id" => $this->content_id])
                    ->one();

                } else if (strpos("field_".$this->unique_field, 'property.')) {
                    $realName = str_replace("property.", "", $this->unique_field);

                    /**
                     * @var $property CmsContentProperty
                     */
                    $property = CmsContentProperty::find()->where(['code' => $realName])->one();
                    $query = $className::find();
                    $className::filterByProperty($query, $property, $uniqueValue);

                    $element = $query->one();
                }
            } else {
                throw new Exception('Не задано уникальное значение');
            }

            if (!$element) {
                /**
                 * @var $element CmsContentElement
                 */
                $element = new $className();
                $element->content_id = $contentId;
                if ($this->new_elements_is_active) {
                    $element->active = "Y";
                } else {
                    $element->active = "N";
                }
            }
        }

        return $element;
    }

    /**
     * Загрузка данных из строки в модель элемента
     *
     * @param                   $number
     * @param                   $row
     * @param CmsContentElement $element
     *
     * @return $this
     */
    protected function _initElementData($number, $row, CmsContentElement $element)
    {

        foreach ($this->matching as $number => $fieldName) {
            //Выбрано соответствие

            $is_update_rewrite = true;

            if ($fieldName) {

                if (is_array($fieldName)) {
                    $is_update_rewrite = (bool) ArrayHelper::getValue($fieldName, 'is_update_rewrite');
                    $fieldName = $fieldName['code'];

                }
                if ($element->isNewRecord) {
                    $this->_initModelByField($element, $fieldName, $row[$number]);
                } else {
                    if ($is_update_rewrite) {
                        $this->_initModelByField($element, $fieldName, $row[$number]);
                    }
                }

            }
        }

        return $this;
    }

    private function _fileHandler($fileSrc)
    {
        /*if (strpos($fileSrc, 'downloader.disk.yandex.ru')) {

            $yandex_download = 'https://cloud-api.yandex.net/v1/disk/public/resources/download?public_key=' . $fileSrc;

            $client = new Client();
            $request = $client->createRequest()->setUrl($yandex_download)
                ->addHeaders(['Accept' => 'application/json; q=1.0, *; q=0.1']);
            var_dump($request);die();

            $response = $request->send();
        }*/

        return $fileSrc;
    }
    /**
     * @param CmsContentElement $element
     * @param                   $row
     * @return $this
     * @throws Exception
     */
    protected function _initImages(CmsContentElement $element, $row)
    {
        $imageData = $this->getValue('image', $row);
        $images = explode(",", $imageData);

        if (count($images) == 0) {
            return $this;
        }

        $firstImageUrl = $images[0];

        if (
            !$element->image &&
            $firstImageUrl) {
            
            $file = \Yii::$app->storage->upload($this->_fileHandler($firstImageUrl), [
                'name' => $element->name,
            ]);
            if ($file) {
                $element->link('image', $file);
            }
        }
        
        if (!$images) {
            return $this;    
        }
        
        //Если картинки уже есть не обновляем их
        if ($element->images) {
            return $this;
        }
        
        ArrayHelper::remove($images, 0);
        //Больше картинок нет, только 1
        if (count($images) == 0) {
            return $this;
        }
        
        foreach ($images as $image) {
            $image = trim($image);
            if (!$image) {
                continue;
            }
            
            $file = \Yii::$app->storage->upload($this->_fileHandler($image), [
                'name' => $element->name,
            ]);

            if ($file) {
                $newGalleryItem = new CmsContentElementImage();
                $newGalleryItem->storage_file_id = $file->id;
                $newGalleryItem->content_element_id = $element->id;
                if (!$newGalleryItem->save()) {
                    throw new Exception(print_r($newGalleryItem->errors, true));
                }
            }
            
        }
        
        return $this;
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

        try {
            $element = $this->_initElement($number, $row);
            $this->_initElementData($number, $row, $element);

            $isUpdate = $element->isNewRecord ? false : true;

            if (!$element->save()) {
                throw new Exception("Ошибка сохранения данных элемента: ".print_r($element->errors, true));
            }
            if (!$element->relatedPropertiesModel->save()) {
                throw new Exception("Ошибка сохранения данных свойств элемента: ".print_r($element->relatedPropertiesModel->errors, true));
            }

            $this->_initImages($element, $row);

            $result->data = $this->matching;
            $result->message = ($isUpdate === true ? "Элемент обновлен" : 'Элемент создан');

            //$element->relatedPropertiesModel->initAllProperties();
            //$rp = Json::encode($element->relatedPropertiesModel->toArray());
            $rp = '';
            $result->html = <<<HTML
Элемент: <a href="{$element->url}" data-pjax="0" target="_blank">{$element->id}</a>
HTML;
            //unset($element->relatedPropertiesModel);
            unset($element);

        } catch (\Exception $e) {
            
            $result->success = false;
            $result->message = $e->getMessage();
            
            if (!$element->isNewRecord) {
                 $result->html = <<<HTML
Элемент: <a href="{$element->url}" data-pjax="0" target="_blank">{$element->id}</a>
HTML;
            }
           
        }


        return $result;
    }


    public function memoryUsage($usage, $base_memory_usage)
    {
        return \Yii::$app->formatter->asSize($usage - $base_memory_usage);
    }

    protected $_headersData = [];

    /**
     * @param array $row
     * @return array
     */
    public function getRowDataWithHeaders($row = [])
    {
        $result = [];

        if (!$this->titles_row_number && $this->titles_row_number != "0") {
            return $row;
        }
        
        $this->titles_row_number = (int) $this->titles_row_number;

        $rows = $this->getCsvColumnsData($this->titles_row_number, $this->titles_row_number);
        if (!$rows) {
            return $row;
        }
        
        $this->_headersData = array_shift($rows);

        if ($this->_headersData) {
            foreach ($this->_headersData as $key => $value)
            {
                $result[$value] = ArrayHelper::getValue($row, $key);
            }
        } else {
            $result = $row;
        }
        
        return $result;
    }
    
    public function execute()
    {
        ini_set("memory_limit", "8192M");
        set_time_limit(0);

        $base_memory_usage = memory_get_usage();
        $this->memoryUsage(memory_get_usage(), $base_memory_usage);

        $this->beforeExecute();

        $rows = $this->getCsvColumnsData($this->startRow, $this->endRow);
        $results = [];
        $totalSuccess = 0;
        $totalErrors = 0;

        $this->result->stdout("\tCSV import: c {$this->startRow} по {$this->endRow}\n");
        $this->result->stdout("\t\t\t" . $this->memoryUsage(memory_get_usage(), $base_memory_usage) . "\n");
        sleep(5);

        foreach ($rows as $number => $data) {
            $baseRowMemory = memory_get_usage();
            $result = $this->import($number, $data);
            if ($result->success) {
                $this->result->stdout("\tСтрока: {$number}: {$result->message}\n");
                $totalSuccess++;
            } else {
                $this->result->stdout("\tСтрока: {$number}: ошибка: {$result->message}\n");
                $totalErrors++;
            }

            //$this->result->stdout("\t\t\t memory: " . $this->memoryUsage(memory_get_usage(), $baseRowMemory) . "\n");
            unset($rows[$number]);
            unset($result);
            //$this->result->stdout("\t\t\t memory: " . $this->memoryUsage(memory_get_usage(), $baseRowMemory) . "\n");

            if ($number % 25 == 0) {
                $this->result->stdout("\t\t\t Total memory: " . $this->memoryUsage(memory_get_usage(), $base_memory_usage) . "\n");
            }
            gc_collect_cycles();
            //$results[$number] = $result;
        }

        return $this->result;
    }


    public function execute2()
    {
        ini_set("memory_limit", "8192M");
        set_time_limit(0);

        $base_memory_usage = memory_get_usage();
        $this->memoryUsage(memory_get_usage(), $base_memory_usage);

        $rows = $this->getCsvColumnsData($this->startRow, $this->endRow);
        $results = [];
        $totalSuccess = 0;
        $totalErrors = 0;

        $this->result->stdout("\tCSV import: c {$this->startRow} по {$this->endRow}\n");

        $counter = 0;
        while(true) {
            $counter++;

            $this->result->stdout("\tСтрока: {$counter}: \n");
            if ($counter < $this->startRow) {
                continue;
            }

            if ($counter > $this->endRow) {
                break;
            }
            
            $rows = $this->getCsvColumnsData($counter, $counter);
            if ($rows) {
                $first = array_shift($rows);
                print_r($first);
            }

        }

        

        return $this->result;
    }
}