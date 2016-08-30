<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 30.08.2016
 */
namespace skeeks\cms\importCsvContent\widgets;

use skeeks\cms\importCsvContent\CsvContentHandler;
use yii\widgets\InputWidget;

/**
 * @property CsvContentHandler $model
 *
 * Class MatchingInput
 *
 * @package skeeks\cms\importCsvContent\widgets
 */
class MatchingInput extends InputWidget
{
    public function init()
    {
        if (!$this->model)
        {
            throw new \InvalidArgumentException;
        }

        parent::init();
    }

    public function run()
    {
        try
        {
            echo $this->render('matching');

        } catch (Exception $e)
        {
            echo $e->getMessage();
        }
    }
}